# 🎃 Halloween BedWars Leaderboard Pedestals — مدل + پلاگین

دکور هالووینی **جمع‌وجور** برای همه‌ی leaderboard های BedWars: یک مدل مشترک که
دورِ ستون متنِ هولوگرامِ خود پلاگین می‌شینه (پایه **زیر** متن، تاج **بالای** متن)
و فقط رون/رنگ شعله/چهره‌ی کدو و فانوس در ۸ استت فرق می‌کنه.

```
         🦇 خفاش در گردش (مدار ۱۸ unit)          
      ╭─── ✦ حلقه‌ی رونیک چرخان + ۸ تیغه ───╮      ← تاج
      │   🎃 فانوس کدویی شناور (آویز)      │
      │ ══ حلقه‌ی رونیک افقی (بالای متن) ══ │
      │      متن هولوگرام پلاگین (۱۳ خط)   │      ← دست‌نخورده، فقط قاب شده
      │ ══ حلقه‌ی رونیک افقی (زیر متن)  ══ │
      │  👻 ارواح چرخان  ◼ کدو  💀 جمجمه   │      ← تزئینات پایه
  ────┴──── پایه‌ی سنگ‌قبری + مه + تار عنکبوت ┴────
```

---

## ۱) هم‌ترازی مدل با هولوگرام پلاگین (مهم)

از سورس پلاگین‌ها:

| منبع | مقدار |
|---|---|
| `sergittos\bedwars\hologram\HologramManager::createLeaderboard()` | ۱۳ خط: تایتل + `Live Rankings` + خط خالی + ۱۰ رنک |
| `Hologram::$lineSpacing` | `0.3` بلاک |
| شروع رندر متن | `pos.y` (پایین‌ترین خط) تا `pos.y + 12 × 0.3 = pos.y + 3.6` |
| جهت متن | `FloatingTextParticle` → همیشه رو به بازیکن (billboard) |

مدل در واحدِ ۱/۱۶ بلاک (هم‌مقیاس با Blockbench/Customies) ساخته شده:

| ناحیه‌ی مدل | ارتفاع مدل (unit) | معادل بلاک |
|---|---|---|
| پایه (زیر متن) | `0 … 24` | `0 … 1.5` |
| **ناحیه‌ی آزاد متن** | `24 … 86` | `1.5 … 5.375` — دقیقاً ۳.۶ بلاک متن |
| تاج (حلقه/فانوس/خفاش) | `88 … 116` | `5.5 … 7.25` |

پس:

```php
$entity = new PedestalKills(Location::fromObject(
    new Vector3($pos->x, $pos->y - HalloweenPedestal::Y_OFFSET, $pos->z),  // Y_OFFSET = 1.5
    $world
));
```

> ناحیه‌ی متن از **هیچ زاویه‌ای** توسط مدل مسدود نمی‌شه: تزئینات پایه در `x/z` کنارِ
> ستون متن هستن و تاج (حلقه/هاب) کاملاً بالای ارتفاع ۳.۶ بلاک متن قرار داره.

---

## ۲) واریانت‌ها (یک مدل مشترک، ۸ اسکین)

| stat (ستون DB) | network id | رون/آیکون | چهره‌ی کدو | رنگ شعله |
|---|---|---|---|---|
| `kills` | `arvan:lb_kills` | 💀 جمجمه | عصبانی | نارنجی مسی |
| `wins` | `arvan:lb_wins` | 🏆 جام | خندان | طلایی |
| `final_kills` | `arvan:lb_final_kills` | 🪓 تبر | ترسیده | قرمز خون |
| `beds_broken` | `arvan:lb_beds_broken` | 🛏 تخت | عصبانی | سبز زمرد |
| `deaths` | `arvan:lb_deaths` | 🦴 قفس دنده | پهن/شیطانی | آبی روح |
| `level` | `arvan:lb_level` | ⭐ ستاره | خندان | بنفش |
| `coins` | `arvan:lb_coins` | 🪙 سکه | ترسیده | کهربایی |
| `win_streak` | `arvan:lb_win_streak` | ⛓ زنجیر | پهن/شیطانی | صورتی |

همه‌ی ۸ واریانت **یک geometry مشترک** دارن (`geometry.arvan_leaderboard`) و فقط تکسچر
عوض می‌شه → یک فایل geo/anim در ریسورس‌پک، ۸ فایل تکسچر، ۸ entity خیلی کوچک.

انیمیشن‌ها (بدون thread اضافه، همه سمت کلاینت):

| بون | حرکت |
|---|---|
| `base`, `rune_ring_low`, `rune_ring_high`, `top_ring` | چرخش آروم افقی (±۳۰°/s) |
| `blade_0..7` (روی `top_ring`) | چرخش با حلقه + پالس مقیاس |
| `lantern` | شناوری + چرخش ملایم (فانوس آویز) |
| `bat_orbit` / `bat_wing_l,r` | مدار + بال‌زدن سریع |
| `pumpkin`, `skull`, `web_a`, `web_b`, `mist` | نفس‌کشیدن/تکان‌خوردن آروم |
| `flame_a,b,c` | فلیکر شعله‌ی شمع‌ها (سه فاز متفاوت) |
| `spirit_orbit` / `spirit_a,b` | دو روح در مدار پایه |
| `dust_orbit_0..2` / `dust_*` | ۱۲ غبار جادویی: ۳ مدار چرخان + bob عمودی (قفس متن) |

---

## ۳) فایل‌ها

```
assets/leaderboard/
├── generate.py                     ← ژنراتور کامل (مدل + تکسچر + پک + رندر پیش‌نمایش)
├── arvan_leaderboard.bbmodel       ← پروژه‌ی Blockbench (قابل ویرایش)
├── arvan_leaderboard.png           ← تکسچر واریانت kills (آتلاس ۲۵۶×۲۵۶)
├── preview_halloween_board.png     ← پیش‌نمایش سه‌ربع با متن ماک هولوگرام
├── preview_halloween_front.png     ← نمای رو‌به‌رو (چک هم‌ترازی خطوط)
├── preview_halloween_decor.png     ← فقط مدل، بدون متن
├── preview_variants.png / preview_texture.png
├── ArvanLeaderboard_RP/            ← ریسورس‌پک (geo/anim/render controller/entity/textures)
├── ArvanLeaderboard_RP.zip / ArvanLeaderboard.mcpack
└── plugin/
    ├── BedWarsLobby/src/sergittos/bedwars/lobby/halloween/
    │   ├── HalloweenPedestal.php              (انتیتی پایه، Y_OFFSET، بدون گرانش/سیو)
    │   ├── PedestalKills.php … PedestalWinStreak.php   (۸ کلاس نازک برای Customies)
    │   ├── HalloweenLeaderboardManager.php    (summon/remove/respawn + ثبت واریانت‌ها)
    │   └── HalloweenLeaderboardCommand.php    (/bwhalloween on|off|respawn|info)
    ├── patches/bedwars-lobby-halloween.patch  ← پچ آماده برای سورس BedWarsLobby
    └── patched/BedWarsLobby/…                 ← همون ۴ فایل، آماده‌ی کپی-پیست
```

پچ این ۴ فایل رو تغییر می‌ده (هر کدوم ۱ تا ۱۰ خط):

| فایل | تغییر |
|---|---|
| `lobby/manager/LobbyManager.php` | ساخت manager + `registerVariants()` در constructor، `summon()` بعد از `createLeaderboard()`، getter `getPedestals()` |
| `lobby/leaderboard/LeaderboardManager.php` | `summon()` در `spawn()` و `remove()` در `removePosition()` |
| `lobby/BedWarsLobby.php` | رجیستر `HalloweenLeaderboardCommand` |
| `resources/config.yml` | کلید `halloween_pedestals: true` |

---

## ۴) نصب

1. **ریسورس‌پک**: `ArvanLeaderboard_RP.zip` را کنار پک‌های موجود لابی بذار
   (`resource_packs/`) و در `resource_packs.yml` / لیست پک‌های سرور اضافه کن
   (یا `ArvanLeaderboard.mcpack` را برای تست روی کلاینت نصب کن).
   *پک مستقل است (UUID جدا)، ولی می‌تونی محتویاتش رو داخل `ArvanLobby_RP` هم merge کنی.*
2. **پلاگین Customies** باید روی سرور لابی نصب باشه (بدون اون، پایه‌ها بی‌صدا غیرفعال
   می‌شن و فقط متن لیدربوردها کار می‌کنه).
3. **سورس BedWarsLobby**:
   ```bash
   cd <پوشه‌ی سورس BedWarsLobby>            # همون‌جایی که plugin.yml هست
   patch -p1 < bedwars-lobby-halloween.patch
   # یا ۴ فایل داخل plugin/patched/BedWarsLobby/ رو دستی کپی کن
   cp -r plugin/BedWarsLobby/src/sergittos/bedwars/lobby/halloween src/sergittos/bedwars/lobby/
   ```
4. سرور را ری‌استارت کن. برای هر لیدربورد یک بار `/lbspawn <stat>` (یا همون لیدربوردهای
   ذخیره‌شده در `holograms.yml`) → پایه‌ها خودشون بالا می‌آن.
5. دستورات: `/bwhalloween info` | `/bwhalloween off/on` | `/bwhalloween respawn`

**نکته‌ی تنظیم ارتفاع**: اگه جایی `lineSpacing` یا تعداد خطوط را عوض کردی، فقط
`HalloweenPedestal::Y_OFFSET` را با فرمول زیر ست کن:

```
Y_OFFSET = 24 / 16 = 1.5          (ارتفاع پایه)
تاج از 5.5 بلاک شروع می‌شه → با 13 خط × 0.3 = 3.6 بلاک متن، بین‌شون 0.4 بلاک فاصله می‌مونه
```

---

## ۵) بازتولید / ویرایش مدل

```bash
python3 -m pip install pillow      # وابستگی: فقط Pillow
cd assets/leaderboard && python3 generate.py
```

`generate.py` سورسِ همه‌چیزه: آتلاس پیکسلی (سنگ، خزه، کدو، شمع، جمجمه، تار، مه،
رون‌های ۸ گانه)، چیدمان بون‌ها/کیوب‌ها، انیمیشن‌ها، خروجی `.bbmodel` + `geo/anim/entity`,
بسته‌بندی پک و رندرهای پیش‌نمایش (با `assets/lobby/render_preview.py`).

برای تغییر ایده: تابع `build_model()` (چیدمان) و `build_atlas()` (تکسچر) و لیست
`VARIANTS` (رنگ/رون/چهره‌ی هر استت) را ویرایش کن.

## ۶) چک‌لیست تست در سرور

- [ ] پک در `resource_packs` لود می‌شه (بدون خطای UUID تکراری).
- [ ] `/lbspawn kills` → پایه دقیقاً ۱.۵ بلاک زیر نقطه، متن وسط قاب، تاج بالای متن.
- [ ] ۱۰ بازیکن اول دیتابیس روی خطوط درست (تایتل بالا، رنک ۱ در پایین).
- [ ] با `/bwhalloween off` فقط دکور حذف می‌شه، متن‌ها سر جاشون می‌مونن.
- [ ] ری‌استارت سرور → بعد از اولین refresh لیدربوردها، پایه‌ها برمی‌گردن.
- [ ] بازیکن جدید که join می‌شه، پایه‌ها را می‌بینه (spawnToAll + chunk load).
