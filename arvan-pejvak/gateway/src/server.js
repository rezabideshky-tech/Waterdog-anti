import crypto from 'node:crypto';
import express from 'express';
import { AccessToken, RoomServiceClient } from 'livekit-server-sdk';
import {
  canHear,
  cleanPlayerState,
  generateConnectionCode,
  isValidConnectionCode,
  makeRoomName,
  secureEqual,
  validDisplayName,
  validIdentifier,
} from './core.js';

const config = {
  port: Number.parseInt(process.env.PORT || '8787', 10),
  controlSecret: process.env.CONTROL_SHARED_SECRET || '',
  livekitApiUrl: process.env.LIVEKIT_API_URL || '',
  livekitWsUrl: process.env.LIVEKIT_WS_URL || '',
  livekitApiKey: process.env.LIVEKIT_API_KEY || '',
  livekitApiSecret: process.env.LIVEKIT_API_SECRET || '',
  radius: Number.parseFloat(process.env.VOICE_RADIUS_BLOCKS || '28'),
  codeTtlSeconds: Number.parseInt(process.env.CODE_TTL_SECONDS || '120', 10),
  sessionTtlSeconds: Number.parseInt(process.env.SESSION_TTL_SECONDS || '7200', 10),
  maxPlayers: Number.parseInt(process.env.MAX_PLAYERS_PER_SERVER || '200', 10),
};

const missingConfig = [
  ['CONTROL_SHARED_SECRET', config.controlSecret],
  ['LIVEKIT_API_URL', config.livekitApiUrl],
  ['LIVEKIT_WS_URL', config.livekitWsUrl],
  ['LIVEKIT_API_KEY', config.livekitApiKey],
  ['LIVEKIT_API_SECRET', config.livekitApiSecret],
].filter(([, value]) => !value).map(([key]) => key);

if (missingConfig.length > 0) {
  console.error(`Missing required environment values: ${missingConfig.join(', ')}`);
  process.exit(1);
}
if (!Number.isFinite(config.radius) || config.radius <= 0 || config.radius > 256) {
  throw new Error('VOICE_RADIUS_BLOCKS must be between 0 and 256');
}

const roomService = new RoomServiceClient(
  config.livekitApiUrl,
  config.livekitApiKey,
  config.livekitApiSecret,
);
const codes = new Map();
const clients = new Map();
const serverPlayers = new Map();
const subscriptionCache = new Map();
const rateWindows = new Map();
const reconcileTimers = new Map();
const reconcilesRunning = new Set();
const codeAlphabet = '23456789ABCDEFGHJKLMNPQRSTUVWXYZ';

const app = express();
app.disable('x-powered-by');
app.use(express.json({ limit: '128kb', strict: true }));

function bearer(req) {
  const value = req.headers.authorization;
  return typeof value === 'string' && value.startsWith('Bearer ') ? value.slice(7) : '';
}

function requireControl(req, res, next) {
  if (!secureEqual(bearer(req), config.controlSecret)) {
    return res.status(401).json({ error: 'unauthorized' });
  }
  next();
}

function consumeRateLimit(key, limit, windowMs) {
  const now = Date.now();
  const current = rateWindows.get(key);
  if (!current || current.until <= now) {
    rateWindows.set(key, { count: 1, until: now + windowMs });
    return true;
  }
  if (current.count >= limit) return false;
  current.count += 1;
  return true;
}

function issueClientSession(code) {
  const room = makeRoomName(code.serverId);
  const token = new AccessToken(config.livekitApiKey, config.livekitApiSecret, {
    identity: code.xuid,
    name: code.name,
    ttl: `${config.sessionTtlSeconds}s`,
  });
  token.addGrant({
    roomJoin: true,
    room,
    canPublish: true,
    canSubscribe: true,
    canPublishData: false,
  });
  const controlToken = crypto.randomBytes(32).toString('base64url');
  return token.toJwt().then((livekitToken) => {
    clients.set(controlToken, {
      serverId: code.serverId,
      xuid: code.xuid,
      name: code.name,
      room,
      expiresAt: Date.now() + config.sessionTtlSeconds * 1000,
    });
    return { livekitToken, controlToken, room };
  });
}

function findClient(req) {
  const token = bearer(req);
  const client = clients.get(token);
  if (!client) return null;
  if (client.expiresAt <= Date.now()) {
    clients.delete(token);
    return null;
  }
  return client;
}

function getPlayers(serverId) {
  return serverPlayers.get(serverId) || new Map();
}

function scheduleProximitySync(serverId) {
  const existing = reconcileTimers.get(serverId);
  if (existing) clearTimeout(existing);
  reconcileTimers.set(serverId, setTimeout(() => {
    reconcileTimers.delete(serverId);
    void reconcileProximity(serverId);
  }, 350));
}

function participantTracks(participant) {
  if (!Array.isArray(participant.tracks)) return [];
  // VoiceCraft publishes audio only; filtering by SID keeps this robust across
  // LiveKit protocol revisions where track-source enum values can differ.
  return participant.tracks
    .filter((track) => typeof track?.sid === 'string' && track.sid.length > 0)
    .map((track) => track.sid);
}

async function reconcileProximity(serverId) {
  if (reconcilesRunning.has(serverId)) return;
  reconcilesRunning.add(serverId);
  try {
    const room = makeRoomName(serverId);
    const participants = await roomService.listParticipants(room);
    const playerStates = getPlayers(serverId);
    const presentIds = new Set(participants.map((participant) => participant.identity));

    for (const participant of participants) {
      const listenerId = participant.identity;
      const listener = playerStates.get(listenerId);
      const desired = new Set();
      if (listener && Date.now() - listener.updatedAt <= 10_000) {
        for (const speaker of participants) {
          if (speaker.identity === listenerId) continue;
          const speakerState = playerStates.get(speaker.identity);
          if (!speakerState || Date.now() - speakerState.updatedAt > 10_000) continue;
          if (!canHear(listener, speakerState, config.radius)) continue;
          for (const sid of participantTracks(speaker)) desired.add(sid);
        }
      }

      const key = `${room}:${listenerId}`;
      const old = subscriptionCache.get(key) || new Set();
      const subscribe = [...desired].filter((sid) => !old.has(sid));
      const unsubscribe = [...old].filter((sid) => !desired.has(sid));
      try {
        if (subscribe.length > 0) await roomService.updateSubscriptions(room, listenerId, subscribe, true);
        if (unsubscribe.length > 0) await roomService.updateSubscriptions(room, listenerId, unsubscribe, false);
        subscriptionCache.set(key, desired);
      } catch (error) {
        console.warn(`Proximity subscription update failed for ${listenerId}: ${error.message}`);
      }
    }

    // Remove cached listener state after a participant leaves the room.
    for (const key of subscriptionCache.keys()) {
      if (key.startsWith(`${room}:`) && !presentIds.has(key.slice(room.length + 1))) {
        subscriptionCache.delete(key);
      }
    }
  } catch (error) {
    // A room does not exist until its first mobile client joins. This is normal.
    if (!String(error?.message || '').toLowerCase().includes('not found')) {
      console.warn(`Proximity sync failed for ${serverId}: ${error.message}`);
    }
  } finally {
    reconcilesRunning.delete(serverId);
  }
}

app.get('/health', (_req, res) => {
  res.json({ ok: true, service: 'arvan-pejvak-gateway', time: new Date().toISOString() });
});

app.post('/v1/pocketmine/code', requireControl, async (req, res) => {
  const { serverId, xuid, name } = req.body || {};
  if (!validIdentifier(serverId, 64) || !validIdentifier(xuid) || !validDisplayName(name)) {
    return res.status(400).json({ error: 'invalid_player' });
  }
  const ip = req.ip || req.socket.remoteAddress || 'unknown';
  if (!consumeRateLimit(`code:${ip}`, 30, 60_000)) {
    return res.status(429).json({ error: 'rate_limited' });
  }

  let code = '';
  for (let attempt = 0; attempt < 8; attempt += 1) {
    code = Array.from({ length: 8 }, () => codeAlphabet[crypto.randomInt(codeAlphabet.length)]).join('');
    if (!codes.has(code)) break;
    code = '';
  }
  if (!code) return res.status(503).json({ error: 'code_generation_failed' });

  const expiresAt = Date.now() + config.codeTtlSeconds * 1000;
  codes.set(code, { serverId, xuid, name: name.trim().normalize('NFC').slice(0, 36), expiresAt });
  res.json({ code, expiresAt, expiresInSeconds: config.codeTtlSeconds });
});

app.post('/v1/mobile/exchange', async (req, res) => {
  const ip = req.ip || req.socket.remoteAddress || 'unknown';
  if (!consumeRateLimit(`exchange:${ip}`, 20, 60_000)) {
    return res.status(429).json({ error: 'rate_limited' });
  }
  const codeText = String(req.body?.code || '').replace(/\s+/g, '').toUpperCase();
  if (!isValidConnectionCode(codeText)) return res.status(400).json({ error: 'invalid_code' });
  const code = codes.get(codeText);
  if (!code || code.expiresAt <= Date.now()) {
    codes.delete(codeText);
    return res.status(404).json({ error: 'code_expired_or_missing' });
  }
  codes.delete(codeText); // single-use: avoid replaying a player-link code

  try {
    const { livekitToken, controlToken, room } = await issueClientSession(code);
    res.json({
      livekitUrl: config.livekitWsUrl,
      token: livekitToken,
      controlToken,
      room,
      identity: code.xuid,
      displayName: code.name,
      expiresAt: Date.now() + config.sessionTtlSeconds * 1000,
    });
  } catch (error) {
    console.error(`Could not issue LiveKit session: ${error.message}`);
    res.status(503).json({ error: 'voice_service_unavailable' });
  }
});

app.post('/v1/pocketmine/presence', requireControl, (req, res) => {
  const { serverId, players } = req.body || {};
  if (!validIdentifier(serverId, 64) || !Array.isArray(players) || players.length > config.maxPlayers) {
    return res.status(400).json({ error: 'invalid_presence' });
  }
  const now = Date.now();
  const clean = new Map();
  for (const raw of players) {
    const state = cleanPlayerState(raw, serverId, now);
    if (state) clean.set(state.xuid, state);
  }
  serverPlayers.set(serverId, clean);
  scheduleProximitySync(serverId);
  res.json({ accepted: clean.size, ignored: players.length - clean.size, receivedAt: now });
});

app.get('/v1/mobile/nearby', (req, res) => {
  const client = findClient(req);
  if (!client) return res.status(401).json({ error: 'session_expired' });
  const players = getPlayers(client.serverId);
  const self = players.get(client.xuid);
  if (!self || Date.now() - self.updatedAt > 10_000) {
    return res.json({ state: 'waiting_for_game_presence', players: [] });
  }
  const nearby = [...players.values()]
    .filter((other) => canHear(self, other, config.radius))
    .map((other) => ({
      identity: other.xuid,
      name: other.name,
      distance: Math.round(Math.hypot(self.x - other.x, self.y - other.y, self.z - other.z) * 10) / 10,
      world: other.world,
    }))
    .sort((a, b) => a.distance - b.distance)
    .slice(0, 64);
  scheduleProximitySync(client.serverId);
  res.json({ state: 'ready', players: nearby, radius: config.radius });
});

app.delete('/v1/mobile/session', (req, res) => {
  const token = bearer(req);
  const client = clients.get(token);
  if (!client) return res.status(204).end();
  clients.delete(token);
  scheduleProximitySync(client.serverId);
  res.status(204).end();
});

app.use((error, _req, res, _next) => {
  if (error?.type === 'entity.too.large') return res.status(413).json({ error: 'request_too_large' });
  if (error instanceof SyntaxError) return res.status(400).json({ error: 'invalid_json' });
  console.error(`Unhandled API error: ${error?.message || 'unknown'}`);
  res.status(500).json({ error: 'internal_error' });
});

const server = app.listen(config.port, '0.0.0.0', () => {
  console.log(`Arvan Pejvak gateway listening on 0.0.0.0:${config.port}`);
});

function shutdown() {
  server.close(() => process.exit(0));
}
process.on('SIGTERM', shutdown);
process.on('SIGINT', shutdown);

// Periodically clear expired one-time codes and mobile control sessions.
setInterval(() => {
  const now = Date.now();
  for (const [code, value] of codes) if (value.expiresAt <= now) codes.delete(code);
  for (const [token, value] of clients) if (value.expiresAt <= now) clients.delete(token);
  for (const [key, value] of rateWindows) if (value.until <= now) rateWindows.delete(key);
}, 30_000).unref();
