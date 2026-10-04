/* main.js — راه‌اندازی بازی، حلقهٔ اصلی، ماشین حالت و پیوند همهٔ بخش‌ها */

import { LEVELS, THEMES, CHARACTERS, VERSION, GAME_TITLE, ACHIEVEMENTS, POWER } from './config.js';
import { buildSprites, buildBossSprites, Sprites as Sprite } from './sprites.js';
import { loadSave, saveGame, fa, clamp } from './utils.js';
import { Sound } from './audio.js';
import { Input } from './input.js';
import { UI } from './ui.js';
import { World } from './game.js';
import { Renderer, VIEW_H } from './render.js';

/* ------------------------------ وضعیت کلی ------------------------------- */
const App = {
  save: loadSave(),
  renderer: null,
  input: null,
  ui: null,
  world: null,
  state: 'loading',
  lastTime: 0,
  accum: 0,
  endless: false,
  levelIndex: 0,
  menuWorld: null,
  fpsSamples: [],
  lowQuality: false,
};

function $(id) { return document.getElementById(id); }

/* ------------------------------- آماده‌سازی ------------------------------ */
async function boot() {
  const canvas = $('game');
  App.renderer = new Renderer(canvas);
  App.input = new Input(document.body);
  App.ui = new UI(App.save, {
    onStartLevel: (i) => startLevel(i),
    onBuy: (item) => buyItem(item),
    onSelectChar: (c, unlocked) => selectCharacter(c, unlocked),
    onSfx: (name) => Sound[name]?.(),
  });

  App.ui.setLoading(0.15, 'رندر اسپرایت‌ها…');
  await nextFrame();
  buildSprites();
  buildBossSprites();
  Sprite.item = Sprite.item || {};

  App.ui.setLoading(0.45, 'ساخت منوی مرحله…');
  await nextFrame();
  App.renderer.buildAtlas(THEMES[LEVELS[0].theme]);

  App.ui.setLoading(0.7, 'آماده‌سازی موسیقی…');
  await nextFrame();
  App.ui.fillSettings();

  bindEvents();
  resize();

  App.ui.setLoading(1, 'آماده!');
  buildMenuWorld();
  App.state = 'menu';
  App.ui.show('screen-loading');
  requestAnimationFrame(loop);
}

function nextFrame() { return new Promise((r) => requestAnimationFrame(() => r())); }

/** صحنهٔ پس‌زمینهٔ منو (مرحلهٔ ۱ به‌عنوان نمایش) */
function buildMenuWorld() {
  App.menuWorld = new World({
    save: App.save,
    character: currentCharacter(),
    levelIndex: 0,
    view: { w: App.renderer.viewW, h: VIEW_H },
    onEvent: () => {},
  });
  App.menuWorld.player.frozen = true;
  App.menuWorld.player.invuln = 999;
  App.menuWorld.paused = true;
}

function currentCharacter() {
  return CHARACTERS.find((c) => c.id === App.save.character) || CHARACTERS[0];
}

/* --------------------------------- رخدادها ------------------------------- */
function bindEvents() {
  for (const btn of document.querySelectorAll('[data-action]')) {
    btn.addEventListener('click', () => {
      const a = btn.dataset.action;
      Sound.resume(); Sound.uiClick();
      switch (a) {
        case 'play': startLevel(Math.min(App.save.unlockedLevel - 1, LEVELS.length - 1)); break;
        case 'map': App.ui.renderMap(); App.ui.show('screen-map'); break;
        case 'endless': startEndless(); break;
        case 'shop': App.ui.renderShop(); App.ui.show('screen-shop'); break;
        case 'chars': App.ui.renderChars(); App.ui.show('screen-chars'); break;
        case 'ach': App.ui.renderAchievements(); App.ui.show('screen-ach'); break;
        case 'settings': App.ui.fillSettings(); App.ui.show('screen-settings'); break;
        case 'help': App.ui.show('screen-help'); break;
        case 'start-level': startLevel(Math.min(App.save.unlockedLevel - 1, LEVELS.length - 1)); break;
        case 'back': goMenu(); break;
        default: break;
      }
    });
  }
  $('btn-sound-start').addEventListener('click', () => {
    Sound.resume();
    Sound.setSfx(App.save.settings.sfx);
    Sound.setMusic(App.save.settings.music);
    if (App.save.settings.music) Sound.startMusic('mahur');
    if (!App.save.seenIntro) { App.save.seenIntro = true; saveGame(App.save); App.ui.show('screen-intro'); }
    else { App.ui.renderTitle(); App.ui.show('screen-title'); App.state = 'menu'; App.ui.hideScreens(); App.ui.renderTitle(); App.ui.show('screen-title'); }
  });
  $('btn-pause').addEventListener('click', () => togglePause());
  $('btn-resume').addEventListener('click', () => togglePause(false));
  $('btn-restart').addEventListener('click', () => { App.ui.hideScreens(); startLevel(App.levelIndex); });
  $('btn-quit').addEventListener('click', () => goMenu());
  $('btn-next').addEventListener('click', () => {
    const next = App.levelIndex + 1;
    if (next < LEVELS.length) startLevel(next);
    else goMenu();
  });
  $('btn-again').addEventListener('click', () => startLevel(App.levelIndex));
  $('btn-menu').addEventListener('click', () => goMenu());
  $('btn-retry').addEventListener('click', () => { App.endless ? startEndless() : startLevel(App.levelIndex); });
  $('btn-go-menu').addEventListener('click', () => goMenu());
  $('btn-reset').addEventListener('click', () => {
    if (!confirm('همهٔ پیشرفت پاک شود؟')) return;
    App.save = loadSave();
    for (const k in App.save) delete App.save[k];
    Object.assign(App.save, loadSave(), { bank: 0, best: {}, stars: {}, unlockedLevel: 1, owned: [], achievements: [], totalMushrooms: 0, totalCoins: 0, totalKills: 0, endlessBest: 0, livesBonus: 0, character: 'koko' });
    saveGame(App.save);
    App.ui.renderTitle(); App.ui.fillSettings();
    App.ui.toast('پیشرفت پاک شد');
  });

  // کلیدهای میان‌بر
  window.addEventListener('keydown', (e) => {
    if (App.state === 'playing' && (e.code === 'Escape' || e.code === 'KeyP')) togglePause();
    if (App.state === 'menu' && e.code === 'Enter') startLevel(Math.min(App.save.unlockedLevel - 1, LEVELS.length - 1));
    if (e.code === 'KeyM') toggleMusic();
  });

  // تنظیمات
  const setMap = { 'set-music': 'music', 'set-sfx': 'sfx', 'set-vibrate': 'vibrate', 'set-lefthanded': 'leftHanded', 'set-shadows': 'shadows' };
  for (const id in setMap) {
    const el = $(id);
    if (!el) continue;
    el.addEventListener('change', () => {
      App.save.settings[setMap[id]] = el.checked;
      saveGame(App.save);
      if (id === 'set-music') Sound.setMusic(el.checked);
      if (id === 'set-sfx') Sound.setSfx(el.checked);
      if (id === 'set-lefthanded') applyLayout();
      Sound.uiClick();
    });
  }
  const q = $('set-quality');
  q?.addEventListener('change', () => { App.save.settings.quality = q.value; saveGame(App.save); applyQuality(); });

  // لمس و کلیک روی صحنه برای شروع صدا
  window.addEventListener('pointerdown', () => Sound.resume(), { once: false });

  window.addEventListener('resize', resize);
  window.addEventListener('orientationchange', () => setTimeout(resize, 250));
  document.addEventListener('visibilitychange', () => { if (document.hidden && App.state === 'playing') togglePause(true); });

  App.input.bindTouchButtons();
  $('touch-controls').classList.add('hidden');
  if (navigator.serviceWorker) {
    navigator.serviceWorker.register('sw.js').catch(() => {});
  }
}

function applyLayout() {
  const tc = $('touch-controls');
  tc.classList.toggle('lefty', !!App.save.settings.leftHanded);
}
function applyQuality() {
  const q = App.save.settings.quality;
  App.lowQuality = q === 'low';
}

function resize() {
  const dpr = clamp(window.devicePixelRatio || 1, 1, 2);
  const view = App.renderer.resize(window.innerWidth, window.innerHeight, dpr);
  if (App.world) App.world.opts.view = view;
  if (App.menuWorld) App.menuWorld.opts.view = view;
  const hint = $('rotate-hint');
  if (hint) hint.classList.toggle('hidden', !(window.innerHeight > window.innerWidth && window.innerWidth < 520 && App.state === 'playing'));
}

/* --------------------------------- خرید -------------------------------- */
function buyItem(item) {
  if (App.save.bank < item.price) { Sound.denied(); App.ui.toast('سکه کافی نیست 🪙'); return; }
  App.save.bank -= item.price;
  if (item.id === 'life') {
    App.save.livesBonus = (App.save.livesBonus || 0) + 1;
  } else if (item.char) {
    App.save.owned.push('char_' + item.char);
  } else {
    App.save.owned.push(item.id);
  }
  if (!App.save.owned.includes(item.id) && item.id !== 'life') {} 
  saveGame(App.save);
  Sound.buy();
  App.ui.toast('خریداری شد: ' + item.name + ' ✅');
  App.ui.renderShop();
  App.ui.renderTitle();
}

function selectCharacter(c, unlocked) {
  if (!unlocked) {
    if (App.save.bank < c.unlock) { Sound.denied(); App.ui.toast('سکه کافی نیست 🪙'); return; }
    App.save.bank -= c.unlock;
    App.save.owned.push('char_' + c.id);
    Sound.buy();
    App.ui.toast(c.name + ' باز شد! 🎉');
  } else {
    Sound.uiClick();
  }
  App.save.character = c.id;
  saveGame(App.save);
  App.ui.renderChars();
  App.ui.renderTitle();
}

/* -------------------------------- شروع مرحله ----------------------------- */
function startLevel(index) {
  App.levelIndex = index;
  App.endless = false;
  const view = { w: App.renderer.viewW, h: VIEW_H };
  App.world = new World({
    save: App.save, character: currentCharacter(), levelIndex: index, view,
    onEvent: onWorldEvent,
  });
  beginPlay();
  const th = THEMES[LEVELS[index].theme];
  Sound.setTheme(th.music, index < 4 ? 112 : 122);
  if (App.save.settings.music) Sound.startMusic(th.music);
}

function startEndless() {
  App.endless = true;
  App.ui.hideScreens();
  const view = { w: App.renderer.viewW, h: VIEW_H };
  const idx = Math.min(LEVELS.length - 1, Math.max(0, App.save.unlockedLevel - 2));
  App.world = new World({ save: App.save, character: currentCharacter(), levelIndex: idx, endless: true, view, onEvent: onWorldEvent });
  beginPlay();
  Sound.setTheme('endless', 132);
  if (App.save.settings.music) Sound.startMusic('endless');
}

function beginPlay() {
  App.state = 'playing';
  App.ui.hideScreens();
  $('touch-controls').classList.remove('hidden');
  $('btn-pause').classList.remove('hidden');
  $('rotate-hint').classList.toggle('hidden', !(window.innerHeight > window.innerWidth && window.innerWidth < 520));
  App.input.clear();
  App.accum = 0;
  App.lastTime = performance.now();
}

function togglePause(force) {
  if (App.state !== 'playing' && App.state !== 'paused') return;
  const paused = force === undefined ? App.state === 'playing' : force;
  App.state = paused ? 'paused' : 'playing';
  App.world.paused = paused;
  if (paused) { App.ui.show('screen-pause'); $('btn-pause').classList.add('hidden'); Sound.pauseBlip(); }
  else { App.ui.hideScreens(); $('btn-pause').classList.remove('hidden'); App.lastTime = performance.now(); }
}

function goMenu() {
  App.state = 'menu';
  App.world = null;
  App.ui.hideScreens();
  $('touch-controls').classList.add('hidden');
  $('btn-pause').classList.add('hidden');
  buildMenuWorld();
  App.ui.renderTitle();
  App.ui.show('screen-title');
  if (App.save.settings.music) Sound.startMusic('mahur');
}

/* ------------------------------ رخدادهای دنیا ---------------------------- */
function onWorldEvent(type, data) {
  switch (type) {
    case 'coin':
      if (data.coins > 0 && data.coins % 25 === 0) App.ui.vibrate(12);
      break;
    case 'stomp': App.ui.vibrate(14); break;
    case 'hurt': App.ui.vibrate(40); break;
    case 'death': App.ui.vibrate([40, 60, 40]); break;
    case 'bossDefeated':
      checkAchievement(App.world.boss.type === 'div' ? 'boss1' : 'boss2');
      App.ui.toast('🎉 رئیس شکست خورد!');
      break;
    case 'mushroom':
      App.save.totalMushrooms++;
      if (App.save.totalMushrooms >= 200) checkAchievement('allMush');
      break;
    case 'combo5': checkAchievement('combo5'); break;
    case 'levelComplete': finishLevel(data); break;
    case 'gameover': onGameOver(); break;
    case 'flag': App.ui.vibrate(20); break;
    case 'flagLocked':
      App.ui.vibrate([20, 40, 20]);
      App.ui.toast('🔒 اول رئیس را شکست بده!', 2000);
      Sound.denied();
      break;
    default: break;
  }
}

function checkAchievement(id) {
  if (App.save.achievements.includes(id)) return;
  App.save.achievements.push(id);
  saveGame(App.save);
  const a = ACHIEVEMENTS.find((x) => x.id === id);
  if (a) { App.ui.toast(`🏆 دستاورد: ${a.name}`); Sound.oneUp(); }
}

function finishLevel(info) {
  const i = App.levelIndex;
  App.save.bank += info.coins;
  App.save.totalCoins += info.coins;
  App.save.totalKills += info.kills;
  if (!App.save.best[i] || info.score > App.save.best[i]) App.save.best[i] = info.score;
  if (!App.save.stars[i] || info.stars > App.save.stars[i]) App.save.stars[i] = info.stars;
  const lv = LEVELS[i];
  App.save.unlockedLevel = Math.max(App.save.unlockedLevel, Math.min(LEVELS.length, lv.n + 1));
  if (lv.n === 1) checkAchievement('firstStep');
  if (info.coins >= 50) checkAchievement('coin50');
  if (info.hurt === 0) checkAchievement('noHit');
  const allStars = LEVELS.every((_, k) => (App.save.stars[k] || 0) >= 1);
  if (allStars) checkAchievement('perfect8');
  saveGame(App.save);
  App.state = 'complete';
  info.hasNext = i + 1 < LEVELS.length;
  App.ui.renderComplete(info);
  App.ui.show('screen-complete');
  $('touch-controls').classList.add('hidden');
  $('btn-pause').classList.add('hidden');
  if (allStars && i === LEVELS.length - 1) {
    App.ui.toast('🏆 قهرمان ایران شدی!', 3000);
  }
}

function onGameOver() {
  App.state = 'gameover';
  $('touch-controls').classList.add('hidden');
  $('btn-pause').classList.add('hidden');
  if (App.endless) {
    const score = App.world.score;
    if (score > (App.save.endlessBest || 0)) { App.save.endlessBest = score; saveGame(App.save); }
    if (score >= 1000) checkAchievement('endless1k');
    App.ui.renderGameOver('♾️ پایان دوی بی‌پایان', `امتیاز: ${fa(score)}\nرکورد شما: ${fa(App.save.endlessBest || 0)}`);
  } else {
    App.ui.renderGameOver('💀 بازی تمام شد', `امتیاز این مرحله: ${fa(App.world.score)} — باز هم تلاش کن!`);
  }
  saveGame(App.save);
  App.ui.show('screen-gameover');
}

function toggleMusic() {
  App.save.settings.music = !App.save.settings.music;
  Sound.setMusic(App.save.settings.music);
  saveGame(App.save);
  App.ui.fillSettings();
  App.ui.toast(App.save.settings.music ? '🎵 موسیقی روشن' : '🔇 موسیقی خاموش');
}

/* -------------------------------- حلقهٔ بازی ----------------------------- */
function loop(now) {
  requestAnimationFrame(loop);
  const dtReal = Math.min(0.05, (now - App.lastTime) / 1000 || 0);
  App.lastTime = now;
  applyQuality();

  if (App.state === 'playing' && App.world) {
    const jd = App.input.poll();
    const step = 1 / 60;
    App.accum += dtReal;
    let guard = 0;
    while (App.accum >= step && guard < 5) {
      App.world.update(step, App.input);
      App.accum -= step;
      guard++;
    }
    App.world.renderDt = dtReal;
  } else if (App.menuWorld) {
    App.menuWorld.updateCamera(1, true);
    App.menuWorld.renderDt = dtReal;
  }

  // رندر
  if (App.state === 'playing' || App.state === 'paused' || App.state === 'complete' || App.state === 'gameover') {
    if (App.world) App.renderer.render(App.world, dtReal);
  } else if (App.menuWorld) {
    // صحنهٔ پس‌زمینهٔ منو با حرکت آرام
    App.menuWorld.time = (App.menuWorld.time || 0) + dtReal;
    App.menuWorld.camera.x += 12 * dtReal;
    if (App.menuWorld.camera.x > 400) App.menuWorld.camera.x = 0;
    App.renderer.render(App.menuWorld, dtReal);
  }

  App.fpsSamples.push(dtReal);
  if (App.fpsSamples.length > 60) App.fpsSamples.shift();
}

/* ---------------------------------- آغاز --------------------------------- */
let booted = false;
function bootOnce() { if (booted) return; booted = true; boot(); }
window.addEventListener('load', bootOnce);
window.addEventListener('DOMContentLoaded', bootOnce);
/* دسترسی عمومی برای تست خودکار و اشکال‌زدایی */
if (typeof window !== 'undefined') window.Gharchekhor = App;

if (document.readyState === 'complete' || document.readyState === 'interactive') bootOnce();

export { App };
