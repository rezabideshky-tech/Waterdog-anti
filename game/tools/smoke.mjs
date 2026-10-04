/* tools/smoke.mjs — آزمون دود واقعی: index.html را در DOM ساختگی بار می‌کند،
   main.js را اجرا می‌کند، با دکمه‌های لمسی بازی می‌کند و اسکرین‌شات می‌گیرد.
   اجرا: node tools/smoke.mjs                                        */
process.stdout.write('smoke: start\n');
import { installDom, saveCanvasPng, registerFonts } from './harness.mjs';
import { readFileSync, mkdirSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import { dirname, join } from 'node:path';

const here = dirname(fileURLToPath(import.meta.url));
const outDir = join(here, '../.arena/tmp');
mkdirSync(outDir, { recursive: true });

const html = readFileSync(join(here, '../www/index.html'), 'utf8');
const { win } = installDom({ html });
win.innerWidth = 880;
win.innerHeight = 480;
globalThis.innerWidth = 880;
globalThis.innerHeight = 480;
registerFonts(join(here, '../www/assets/fonts'));

const problems = [];
const origError = console.error;
console.error = (...a) => { problems.push('console.error: ' + a.map(String).join(' ')); origError(...a); };
process.on('uncaughtException', (e) => { problems.push('uncaught: ' + (e && e.stack ? e.stack.split('\n').slice(0, 3).join(' | ') : String(e))); });
process.on('unhandledRejection', (e) => { problems.push('rejection: ' + e); });

const sleep = (ms) => new Promise((r) => setTimeout(r, ms));
const $ = (id) => document.getElementById(id);
const clickAction = (action) => {
  const el = document.querySelector(`[data-action="${action}"]`);
  if (!el) { problems.push(`دکمهٔ ${action} پیدا نشد`); return false; }
  el.dispatchEvent('click', {});
  return true;
};
const press = (id, on) => {
  const el = $(id);
  if (!el) { problems.push(`دکمهٔ لمسی ${id} پیدا نشد`); return; }
  el.dispatchEvent(on ? 'mousedown' : 'mouseup', {});
};
const shot = (name) => {
  const cv = $('game');
  if (cv && cv.toBuffer) saveCanvasPng(cv, join(outDir, `smoke-${name}.png`));
};

await import('../www/js/main.js');
await sleep(500);
const App = win.Gharchekhor;
const log = (...a) => console.log('•', ...a);

if (!App) { problems.push('window.Gharchekhor ساخته نشد'); }
log('state after boot =', App && App.state);
shot('01-menu');

// --- شروع بازی از دکمهٔ «بازی جدید» ---
clickAction('play');
await sleep(300);
log('state after play =', App.state, '| level =', App.levelIndex);
const x0 = App.world ? App.world.player.x : 0;

// --- بازی با دکمه‌های لمسی: راست + دویدن + پرش‌های مکرر ---
const startHp = App.world ? App.world.player.power : -1;
press('btn-right', true);
press('btn-run', true);
for (let i = 0; i < 220; i++) {
  if (i % 14 === 0) { press('btn-jump', true); }
  if (i % 14 === 7) { press('btn-jump', false); }
  await sleep(16);
}
press('btn-jump', false);
shot('02-playing');
const x1 = App.world ? App.world.player.x : 0;
log(`حرکت بازیکن: ${Math.round(x0)} → ${Math.round(x1)} px (${x1 > x0 + 40 ? 'درست' : 'تکان نخورد!'})`);
if (!(x1 > x0 + 40)) problems.push('بازیکن با دکمهٔ لمسی حرکت نکرد');

// --- گرافیک، صدا و چرخهٔ بازی: چند صحنهٔ دیگر ---
press('btn-right', false);
press('btn-run', false);
clickAction('shop'); await sleep(120); shot('03-shop');
clickAction('back'); await sleep(80);
clickAction('settings'); await sleep(120);
clickAction('back'); await sleep(80);
clickAction('map'); await sleep(120); shot('04-map');
clickAction('back'); await sleep(80);
App.save.bank = 5000;
clickAction('shop');
const shopBtns = document.querySelectorAll('#shop-list button');
if (!shopBtns.length) problems.push('کارت‌های فروشگاه ساخته نشدند');
log('دکمه‌های فروشگاه =', shopBtns.length);
if (shopBtns.length) shopBtns[0].dispatchEvent('click', {});
await sleep(80);
clickAction('back'); await sleep(80);
clickAction('endless'); await sleep(300);
log('state in endless =', App.state);
shot('05-endless');
press('btn-right', true);
for (let i = 0; i < 90; i++) { if (i % 12 === 0) press('btn-jump', true); if (i % 12 === 6) press('btn-jump', false); await sleep(16); }
press('btn-right', false); press('btn-jump', false);
// --- مکث و منو ---
if (App.state === 'playing') { $('btn-pause').dispatchEvent('click', {}); await sleep(120); }
press('btn-resume', false) || $('btn-resume').dispatchEvent('click', {});
await sleep(120);
$('btn-quit').dispatchEvent('click', {});
await sleep(200);
log('state after quit =', App.state);
shot('06-menu');

// --- مرحلهٔ رئیس‌دار با پرش سریع ---
App.save.unlockedLevel = 8;
App.save.owned = ['permFeather'];
clickAction('map');
await sleep(120);
const levelBtns = document.querySelectorAll('#map-grid button');
log('دکمه‌های نقشه =', levelBtns.length);
if (levelBtns.length) levelBtns[levelBtns.length - 1].dispatchEvent('click', {});
await sleep(200);
log('state =', App.state, '| level =', App.levelIndex);
if (App.state !== 'playing' || App.levelIndex !== 7) problems.push('شروع مرحلهٔ ۸ از نقشه کار نکرد');
press('btn-right', true); press('btn-run', true);
for (let i = 0; i < 60; i++) { if (i % 10 === 0) press('btn-jump', true); if (i % 10 === 5) press('btn-jump', false); await sleep(16); }
shot('07-level8');
press('btn-right', false);

console.error = origError;
const bad = problems.filter((p) => !/AudioContext|decodeAudio|not implemented/i.test(p));
console.log('');
if (bad.length) {
  console.log(`❌ آزمون دود: ${bad.length} مشکل`);
  for (const b of bad.slice(0, 12)) console.log('   -', b);
  process.exit(1);
}
console.log('✅ آزمون دود پاس شد (بوت، منو، لمس، صحنه‌ها، رئیس، بدون خطا)');
process.exit(0);
