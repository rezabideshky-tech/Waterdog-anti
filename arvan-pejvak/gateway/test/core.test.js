import test from 'node:test';
import assert from 'node:assert/strict';
import {
  canHear,
  cleanPlayerState,
  distance3d,
  generateConnectionCode,
  isValidConnectionCode,
  makeRoomName,
  secureEqual,
  validDisplayName,
  validIdentifier,
} from '../src/core.js';

test('connection codes are random-looking, fixed length, and unambiguous', () => {
  const code = generateConnectionCode();
  assert.equal(code.length, 8);
  assert.equal(isValidConnectionCode(code), true);
  assert.equal(isValidConnectionCode('00000000'), false);
});

test('server room names are stable and do not disclose the configured id', () => {
  assert.equal(makeRoomName('bedwars-1'), makeRoomName('bedwars-1'));
  assert.notEqual(makeRoomName('bedwars-1'), makeRoomName('bedwars-2'));
  assert.match(makeRoomName('bedwars-1'), /^arvan-[a-f0-9]{20}$/);
  assert.equal(makeRoomName('bedwars-1').includes('bedwars'), false);
});

test('proximity is world-scoped and uses three-dimensional distance', () => {
  const a = { xuid: 'player-a', world: 'world', x: 0, y: 64, z: 0 };
  const b = { xuid: 'player-b', world: 'world', x: 3, y: 68, z: 0 };
  assert.equal(distance3d(a, b), 5);
  assert.equal(canHear(a, b, 5), true);
  assert.equal(canHear(a, b, 4.99), false);
  assert.equal(canHear(a, { ...b, world: 'nether' }, 100), false);
  assert.equal(canHear(a, { ...b, xuid: 'player-a' }, 100), false);
});

test('presence validation rejects bad identities/coordinates and normalizes names', () => {
  assert.equal(validIdentifier('xuid:123', 96), true);
  assert.equal(validIdentifier('../bad'), false);
  assert.equal(validDisplayName('  آروان  '), true);
  assert.equal(validDisplayName('bad\nname'), false);
  const state = cleanPlayerState({
    xuid: 'xuid:123', name: '  Cafe\u0301  ', world: 'lobby', x: 1, y: 2, z: 3,
  }, 'main', 10);
  assert.equal(state.name, 'Café');
  assert.equal(state.updatedAt, 10);
  assert.equal(cleanPlayerState({ xuid: 'x', name: 'p', world: 'w', x: Infinity, y: 0, z: 0 }, 's'), null);
});

test('control credentials are compared in constant-time-safe helper', () => {
  assert.equal(secureEqual('secret', 'secret'), true);
  assert.equal(secureEqual('secret', 'secreT'), false);
  assert.equal(secureEqual('secret', 'longer'), false);
});
