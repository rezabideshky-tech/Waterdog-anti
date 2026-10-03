import crypto from 'node:crypto';

const CODE_ALPHABET = '23456789ABCDEFGHJKLMNPQRSTUVWXYZ';

export function validIdentifier(value, maxLength = 96) {
  return typeof value === 'string' && value.length > 0 && value.length <= maxLength &&
    /^[a-zA-Z0-9._:-]+$/.test(value);
}

export function validDisplayName(value) {
  return typeof value === 'string' && value.trim().length > 0 &&
    [...value.trim()].length <= 36 && !/[\u0000-\u001f\u007f]/u.test(value);
}

export function makeRoomName(serverId) {
  const digest = crypto.createHash('sha256').update(String(serverId)).digest('hex').slice(0, 20);
  return `arvan-${digest}`;
}

export function generateConnectionCode(length = 8) {
  let code = '';
  for (let i = 0; i < length; i += 1) {
    code += CODE_ALPHABET[crypto.randomInt(0, CODE_ALPHABET.length)];
  }
  return code;
}

export function isValidConnectionCode(code) {
  return typeof code === 'string' && /^[23456789ABCDEFGHJKLMNPQRSTUVWXYZ]{8}$/.test(code);
}

export function distance3d(a, b) {
  const dx = a.x - b.x;
  const dy = a.y - b.y;
  const dz = a.z - b.z;
  return Math.sqrt(dx * dx + dy * dy + dz * dz);
}

export function canHear(listener, speaker, radius) {
  return Boolean(listener && speaker && listener.world === speaker.world &&
    listener.xuid !== speaker.xuid && distance3d(listener, speaker) <= radius);
}

export function cleanPlayerState(raw, serverId, now = Date.now()) {
  if (!raw || !validIdentifier(raw.xuid) || !validDisplayName(raw.name) ||
      !validIdentifier(raw.world, 64) || ![raw.x, raw.y, raw.z].every(Number.isFinite)) {
    return null;
  }
  return {
    serverId,
    xuid: raw.xuid,
    name: raw.name.trim().normalize('NFC').slice(0, 36),
    world: raw.world,
    x: raw.x,
    y: raw.y,
    z: raw.z,
    updatedAt: now,
  };
}

export function secureEqual(a, b) {
  if (typeof a !== 'string' || typeof b !== 'string') return false;
  const left = Buffer.from(a);
  const right = Buffer.from(b);
  return left.length === right.length && crypto.timingSafeEqual(left, right);
}
