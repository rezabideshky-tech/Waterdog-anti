/* tools/audio-test.mjs — آزمون موتور صدا با یک WebAudio ساختگی و سخت‌گیر
 *
 * چرا؟ چون مسیر صدا در مرورگر واقعی اجرا می‌شود و اگر پارامتری NaN باشد یا
 * AudioParam با مقدار نامعتبر ramp شود، مرورگر (مخصوصاً موبایل) خطا می‌دهد.
 * این آزمون همهٔ افکت‌ها و موسیقی همهٔ دستگاه‌ها را اجرا و بررسی می‌کند.
 *
 * اجرا: node tools/audio-test.mjs
 */
import { readFileSync, readdirSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import { dirname, join } from 'node:path';

const here = dirname(fileURLToPath(import.meta.url));

const { installFakeWebAudio } = await import('./fake-audio.mjs');
const { calls, Ctx: FakeCtx } = installFakeWebAudio();

/* ------------------------------ آزمون ------------------------------ */
const problems = [];
const ok = (label, cond, extra = '') => {
  if (cond) console.log(`  ✅ ${label}${extra ? ' — ' + extra : ''}`);
  else { console.log(`  ❌ ${label}${extra ? ' — ' + extra : ''}`); problems.push(label); }
};

const { AudioEngine, Sound } = await import('../www/js/audio.js');
const config = await import('../www/js/config.js');

console.log('۱) ساختار و اتصال صداها');
ok('init() زمینهٔ صوتی می‌سازد', Sound.init() === true);
ok('init() دوباره زمینهٔ تازه نمی‌سازد', Sound.init() === true && Sound.ctx instanceof FakeCtx);
Sound.resume();
ok('resume() زمینهٔ معلق را باز می‌کند', calls.resume >= 1 && Sound.ctx.state === 'running');

// همهٔ فراخوانی‌های Sound.x( در کد بازی باید متد موجود باشند
const usedNames = new Set();
for (const f of readdirSync(join(here, '../www/js'))) {
  if (!f.endsWith('.js')) continue;
  const src = readFileSync(join(here, '../www/js', f), 'utf8');
  for (const m of src.matchAll(/Sound\.([a-zA-Z_]\w*)\(/g)) if (m[1] !== 'setTheme') usedNames.add(m[1]);
}
const missing = [...usedNames].filter((n) => typeof Sound[n] !== 'function');
ok(`همهٔ ${usedNames.size} صدای استفاده‌شده در بازی موجودند`, missing.length === 0, missing.join(', '));

console.log('\n۲) اجرای همهٔ افکت‌ها (بدون خطای پارامتر)');
const sfx = ['jump', 'bigJump', 'stomp', 'kick', 'coin', 'fireCoin', 'bump', 'brick', 'powerup', 'powerdown',
  'fire', 'hurt', 'die', 'oneUp', 'star', 'checkpoint', 'bossHit', 'bossRoar', 'spring',
  'pauseBlip', 'victory', 'gameOver', 'uiClick', 'uiBack', 'buy', 'denied'];
let failed = [];
for (const name of sfx) {
  const before = calls.osc + calls.buffer;
  try { Sound[name](); } catch (e) { failed.push(`${name}: ${e.message}`); }
  if (calls.osc + calls.buffer === before) failed.push(`${name}: هیچ صدایی تولید نکرد`);
}
ok(`هر ${sfx.length} افکت صدا تولید می‌کند`, failed.length === 0, failed.slice(0, 4).join(' | '));

console.log('\n۳) خاموش‌کردن افکت‌ها');
Sound.setSfx(false);
const beforeMute = calls.osc + calls.buffer;
Sound.coin(); Sound.stomp(); Sound.bossRoar();
ok('با خاموشی افکت‌ها صدایی ساخته نمی‌شود', calls.osc + calls.buffer === beforeMute);
Sound.setSfx(true);

console.log('\n۴) موسیقی همهٔ دستگاه‌های ایرانی');
const themes = [...new Set(Object.values(config.THEMES).map((t) => t.music))];
for (const name of themes) {
  Sound.setTheme(name, 120);
  ok(`دستگاه «${name}» شناسایی می‌شود`, Sound.theme === name, Sound.theme !== name ? `به ${Sound.theme} افتاد` : '');
  const before = calls.osc;
  Sound.startMusic(name);
  for (let step = 0; step < 40; step++) { Sound.ctx.currentTime += 0.06; Sound._schedule(); }
  Sound.stopMusic();
  ok(`موسیقی «${name}» نُت تولید می‌کند`, calls.osc > before, `${calls.osc - before} نُت`);
}
Sound.setTheme('endless', 132);
ok('دستگاه دوی بی‌پایان شناسایی می‌شود', Sound.theme === 'endless');

console.log('\n۵) کلید موسیقی (خاموش/روشن از تنظیمات)');
Sound.startMusic('shur');
ok('startMusic تایمر زمان‌بندی می‌سازد', !!Sound._timer);
Sound.setMusic(false);
ok('setMusic(false) موسیقی را قطع می‌کند', !Sound._timer && Sound.wantMusic === true);
Sound.setMusic(true);
ok('setMusic(true) موسیقی را دوباره شروع می‌کند', !!Sound._timer, 'bug قدیمی: موسیقی دیگر پخش نمی‌شد');
Sound.stopMusic();

console.log('\n۵٫۵) توقف بازی: موسیقی باید قطع و برگردد، ولی تنظیم کاربر حفظ شود');
Sound.setTheme('shur', 116);
Sound.startMusic('shur');
Sound.pauseMusic();
ok('pauseMusic موسیقی را قطع می‌کند', !Sound._timer && Sound.wantMusic === true);
Sound.resumeMusic();
ok('resumeMusic موسیقی را برمی‌گرداند', !!Sound._timer);
Sound.setMusic(false);           // کاربر موسیقی را خاموش می‌کند
Sound.pauseMusic(); Sound.resumeMusic();
ok('اگر کاربر موسیقی را خاموش کرده باشد، توقف/ادامه آن را روشن نمی‌کند', !Sound._timer);
Sound.setMusic(true);
ok('روشن‌کردن دوبارهٔ موسیقی از تنظیمات کار می‌کند', !!Sound._timer);
Sound.stopMusic();

console.log('\n۶) بازگشت از پس‌زمینه (پرش زمانی)');
Sound.startMusic('mahur');
Sound.ctx.currentTime += 30;         // مثل وقتی که صفحه ۳۰ ثانیه در پس‌زمینه بوده
const stamps = [];
const origPlay = Sound._playStep.bind(Sound);
Sound._playStep = (step, time) => { stamps.push(time); origPlay(step, time); };
Sound._schedule();
Sound._playStep = origPlay;
const now = Sound.ctx.currentTime;
ok('هیچ نُتی در گذشته زمان‌بندی نمی‌شود', stamps.every((t) => t >= now), `کمترین فاصله=${(Math.min(...stamps) - now).toFixed(3)}s`);
ok('بعد از پرش زمانی، تعداد نُت‌های عقب‌مانده محدود است', stamps.length <= 12, `${stamps.length} نُت`);
Sound.stopMusic();

console.log('\n۷) پایداری: ۶۰۰ فریم پخش بدون خطا');
let err = null;
try {
  Sound.startMusic('homayoun');
  for (let i = 0; i < 600; i++) {
    Sound.ctx.currentTime += 1 / 60;
    Sound._schedule();
    if (i % 20 === 0) Sound[i % 40 === 0 ? 'coin' : 'jump']();
    if (i % 120 === 0) Sound.startMusic(i % 240 === 0 ? 'mahur' : 'shur');
  }
  Sound.stopMusic();
} catch (e) { err = e; }
ok('پخش طولانی بدون خطا', !err, err ? err.message : `${calls.osc} نوسان‌ساز، ${calls.bufferStart} نمونهٔ صوتی`);

console.log('');
if (problems.length) {
  console.log(`❌ آزمون صدا: ${problems.length} مشکل`);
  for (const p of problems) console.log('   -', p);
  process.exit(1);
}
console.log('✅ موتور صدا سالم است (افکت‌ها، موسیقی، کلیدها، پرش زمانی)');
process.exit(0);
