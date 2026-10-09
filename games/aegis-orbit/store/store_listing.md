# Aegis Orbit — store listing (draft)

Default market: international, English first (see the factory's `markets/international.md`).
Persian listing is included for the Iranian stores and for players who read Persian.

## English
- **Name:** Aegis Orbit
- **Short description (≤80 chars):** EN_SHORT: Block the falling rocks with a rotating shield. Protect the planet.
- **Full description:**
  Aegis Orbit is a one-thumb arcade game. A planet sits in the middle of the screen and a shield
  orbits it. Rocks fall from every direction. Point the shield where the rock will land, block it,
  and build a combo. Every ten blocks in a row charges a Nova that clears the sky.
  Rocks get faster, armoured rocks need two hits, comets arrive fast. Classic mode has no end.
  Daily Sky gives every player the same sky on the same day, one attempt, two lives.
  Eleven achievements, a records board and a short first-run tutorial. No ads, no purchases, no account.
- **Category:** Arcade
- **Permissions:** none required (vibration is optional, used for haptic feedback only)

## Persian (فارسی)
- **نام:** Aegis Orbit (مدار سپر)
- **توضیح کوتاه (≤60 حرف):** FA_SHORT: سپر را بچرخان و سنگ‌ها را روی حلقه متوقف کن
- **توضیح کامل:**
  مدار سپر یک بازی آرکید با یک انگشت است. سیاره‌ای وسط صفحه است و سپری دورش می‌چرخد.
  سنگ‌ها از هر طرف می‌افتند. سپر را به جایی ببر که سنگ به آن‌جا می‌رسد، آن را متوقف کن
  و ترکیب بساز. هر ۱۰ توقف پیاپی یک «نوا» شارژ می‌کند که آسمان را پاک می‌کند.
  سنگ‌ها سریع‌تر می‌شوند، سنگ‌های زرهی دو ضربه می‌خواهند و دنباله‌دارها سریع می‌آیند.
  حالت کلاسیک پایان ندارد. «آسمان روزانه» برای همه‌ی بازیکنان در یک روز آسمان یکسان دارد؛ یک تلاش و دو جان.
  یازده دستاورد، جدول رکوردها و آموزش کوتاه شروع بازی. بدون تبلیغ، بدون خرید درون‌برنامه و بدون حساب کاربری.

## Before publishing (human steps)
- Replace the placeholder package name `com.example.aegisorbit` in `export_presets.cfg`.
- Create a signing keystore outside the project and never commit it.
- Privacy policy: the game collects nothing and has no network code; state that in the policy.
- Screenshots and the 1024 px icon must be captured from a real build (the headless environment cannot render).
