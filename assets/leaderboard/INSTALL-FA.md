# 🎃 BedWarsLobby + دکور هالووینی لیدربوردها — راهنمای نصب (فارسی)

این بسته، **کل پلاگین BedWarsLobby** با تغییرات اعمال‌شده است + ریسورس‌پک مدل‌ها.
هیچ فایلی از قابلیت‌های قبلی حذف نشده؛ فقط ۴ فایل پچ شده و یک پوشه‌ی جدید اضافه شده.

---

## گام ۱ — پلاگین (اجباری)

پوشه‌ی `BedWarsLobby/` را کامل داخل پوشه‌ی `plugins/` سرور لابی بگذار:

```
سرور-لابی/
└── plugins/
    ├── BedWarsCore/          ← قبل از این باید موجود باشه (پلاگین اصلی شما)
    ├── Customies/            ← برای دکور لازمه (اگه نباشه، فقط دکور نمیاد)
    └── BedWarsLobby/         ← همین بسته (plugin.yml + resources/ + src/)
```

سپس سرور را ری‌استارت کن. PocketMine پلاگین را از حالت پوشه هم لود می‌کند
(نیازی به ساخت `.phar` نیست؛ اگر `.phar` می‌خواهی، همین پوشه را با DevTools بساز).

> ⚠️ اگر نسخه‌ی قبلی BedWarsLobby را به‌صورت پوشه/فار دارید، اول آن را پاک یا بکاپ بگیرید
> تا دو نسخه با نام یکسان هم‌زمان لود نشود.

## گام ۲ — ریسورس‌پک (اجباری، وگرنه مدل‌ها دیده نمی‌شوند)

`resource_packs/ArvanLeaderboard_RP.zip` را در پوشه‌ی `resource_packs/` سرور بگذار و
در `resource_packs.yml` هم اضافه/تأیید کن:

```yaml
resource_stack:
  - ArvanLeaderboard_RP.zip
```
(یا محتویات پک را داخل `ArvanLobby_RP` خودت merge کن — UUID پک جداست.)

برای تست روی کلاینت: `ArvanLeaderboard.mcpack` را نصب کن.

## گام ۳ — چک نهایی

- `/lbspawn kills` (یا همان لیدربوردهای ذخیره‌شده‌ات) → باید پایه‌ی هالووینی
  دقیقاً زیر متن و تاج بالای متن ظاهر شود.
- `/bwhalloween info` → وضعیت و لیست ۸ واریانت.
- `/bwhalloween off` → فقط دکور می‌رود، متن لیدربوردها سالماند.
- `/bwhalloween respawn` → همه‌ی پایه‌ها از نو ساخته می‌شوند.

---

## چه چیزی تغییر کرده؟ (۵ فایل)

| فایل | تغییر |
|---|---|
| `lobby/manager/LobbyManager.php` | ساخت `HalloweenLeaderboardManager` + `registerVariants()`، صدا زدن `summon()` بعد از ساخت هولوگرام، getter `getPedestals()` |
| `lobby/leaderboard/LeaderboardManager.php` | `summon()` در `spawn()` (با accessor امن)، حذف دکور در `removePosition()` |
| `lobby/BedWarsLobby.php` | رجیستر دستور `/bwhalloween` + `getLobbyManagerOrNull()` |
| `resources/config.yml` | کلید `halloween_pedestals: true` |
| `…/lobby/halloween/**` (جدید) | ۱۱ فایل: انتیتی پایه، ۸ واریانت، manager، دستور |

تنظیم ارتفاع (اگر `lineSpacing` یا تعداد خطوط هولوگرام را عوض کردی):
`HalloweenPedestal::Y_OFFSET` باید برابر `(ارتفاع پایه مدل) / 16` باشد؛ پیش‌فرض `1.5`.

## رفع اشکال سریع

| مشکل | علت / راه‌حل |
|---|---|
| مدل‌ها دیده نمی‌شوند ولی متن هست | پک در `resource_packs` نصب نشده یا Customies نصب نیست (لاگ سرور را ببین) |
| پلاگین بالا نمی‌آید | BedWarsCore باید نصب باشد (`depend: BedWarsCore`) |
| دکور بالاتر/پایین‌تر از متن است | مقدار `Y_OFFSET` را تنظیم کن (۱.۵ = ۲۴ unit پایه) |
| می‌خواهم کل دکور خاموش باشد | `/bwhalloween off` یا `halloween_pedestals: false` |
