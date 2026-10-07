/* tools/patch-android.mjs — پس از `npx cap add android` پلتفرم اندروید را برای بازی تنظیم می‌کند:
   · قفل چرخش افقی (منظره) · تمام‌صفحه و بی‌نوار · رنگ پس‌زمینهٔ بازی
   اجرا: node tools/patch-android.mjs   (idempotent — چند بار اجرا مشکل ایجاد نمی‌کند) */
import { readFileSync, writeFileSync, existsSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import { dirname, join } from 'node:path';

const here = dirname(fileURLToPath(import.meta.url));
const androidDir = join(here, '../android');
const manifest = join(androidDir, 'app/src/main/AndroidManifest.xml');
const styles = join(androidDir, 'app/src/main/res/values/styles.xml');
const strings = join(androidDir, 'app/src/main/res/values/strings.xml');

if (!existsSync(manifest)) {
  console.error('❌ پوشهٔ android پیدا نشد؛ اول `npx cap add android` را اجرا کن.');
  process.exit(1);
}

let changed = [];

/* --- ۱) چرخش افقی + حالت تیره برای فعالیت اصلی --- */
let m = readFileSync(manifest, 'utf8');
if (!/android:screenOrientation=/.test(m)) {
  m = m.replace(
    /(<activity\b[^>]*?android:name="\.MainActivity")/,
    '$1\n            android:screenOrientation="sensorLandscape"\n            android:resizeableActivity="false"',
  );
  changed.push('screenOrientation');
}
if (!/android:windowSoftInputMode=/.test(m)) {
  m = m.replace(/(<activity\b[^>]*?android:name="\.MainActivity")/, '$1\n            android:windowSoftInputMode="adjustNothing"');
  changed.push('softInput');
}
writeFileSync(manifest, m);

/* --- ۲) تم تمام‌صفحه --- */
if (existsSync(styles)) {
  let st = readFileSync(styles, 'utf8');
  if (!/windowFullscreen/.test(st)) {
    st = st.replace(
      /(<style name="AppTheme\.NoActionBar"[^>]*>)/,
      `$1\n        <item name="android:windowFullscreen">true</item>\n        <item name="android:windowLayoutInDisplayCutoutMode">shortEdges</item>\n        <item name="android:windowBackground">#12102a</item>`,
    );
    writeFileSync(styles, st);
    changed.push('fullscreen theme');
  }
} else {
  console.warn('⚠️ styles.xml پیدا نشد؛ مرحلهٔ تمام‌صفحه رد شد.');
}

/* --- ۳) نام برنامه به فارسی --- */
if (existsSync(strings)) {
  let sr = readFileSync(strings, 'utf8');
  const next = sr.replace(/(<string name="app_name">)[^<]*(<\/string>)/, '$1قارچ‌خور$2')
    .replace(/(<string name="title_activity_main">)[^<]*(<\/string>)/, '$1قارچ‌خور$2');
  if (next !== sr) { writeFileSync(strings, next); changed.push('app name'); }
}

/* --- ۴) آیکون‌های لانچر از آیکون بازی --- */
try {
  const { createCanvas, loadImage } = await import('@napi-rs/canvas');
  const src = join(here, '../www/assets/img/icon-512.png');
  if (existsSync(src)) {
    const img = await loadImage(src);
    const sizes = { mdpi: 48, hdpi: 72, xhdpi: 96, xxhdpi: 144, xxxhdpi: 192 };
    for (const [dens, size] of Object.entries(sizes)) {
      const dir = join(androidDir, `app/src/main/res/mipmap-${dens}`);
      if (!existsSync(dir)) continue;
      const cv = createCanvas(size, size);
      const g = cv.getContext('2d');
      g.imageSmoothingQuality = 'high';
      g.drawImage(img, 0, 0, size, size);
      writeFileSync(join(dir, 'ic_launcher.png'), cv.toBuffer('image/png'));
      // نسخهٔ گرد
      const rc = createCanvas(size, size);
      const rg = rc.getContext('2d');
      rg.save();
      rg.beginPath(); rg.arc(size / 2, size / 2, size / 2, 0, Math.PI * 2); rg.clip();
      rg.drawImage(img, 0, 0, size, size);
      rg.restore();
      writeFileSync(join(dir, 'ic_launcher_round.png'), rc.toBuffer('image/png'));
      writeFileSync(join(dir, 'ic_launcher_foreground.png'), cv.toBuffer('image/png'));
    }
    // تصویر اسپلش راه‌انداز (منظره) از آیکون بازی
    const splashDir = join(androidDir, 'app/src/main/res/drawable');
    if (existsSync(splashDir)) {
      const SW = 1280, SH = 720, icon = 340;
      const cv = createCanvas(SW, SH);
      const g = cv.getContext('2d');
      g.imageSmoothingQuality = 'high';
      const grad = g.createLinearGradient(0, 0, 0, SH);
      grad.addColorStop(0, '#221a4a');
      grad.addColorStop(1, '#12102a');
      g.fillStyle = grad; g.fillRect(0, 0, SW, SH);
      g.drawImage(img, (SW - icon) / 2, (SH - icon) / 2 - 34, icon, icon);
      g.fillStyle = '#ffd166';
      g.font = 'bold 46px sans-serif';
      g.textAlign = 'center';
      g.fillText('قارچ‌خور', SW / 2, SH / 2 + icon / 2 + 40);
      g.fillStyle = '#ffffffaa';
      g.font = '22px sans-serif';
      g.fillText('ماجراهای کوکو در ایران', SW / 2, SH / 2 + icon / 2 + 78);
      writeFileSync(join(splashDir, 'splash.png'), cv.toBuffer('image/png'));
      changed.push('splash screen');
    }
    changed.push('launcher icons');
  }
} catch (e) {
  console.warn('⚠️ ساخت آیکون لانچر ممکن نشد:', e.message);
}

console.log(changed.length ? `✅ اندروید تنظیم شد: ${changed.join('، ')}` : '✅ اندروید از قبل تنظیم شده بود.');
