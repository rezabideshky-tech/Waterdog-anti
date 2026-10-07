# 👑 وصل‌کردن «تاج‌های غول‌پیکر» به BedWarsCore v2 (bedwars_v7) — اختیاری

پلاگین `ArvanLeaderboards` به‌صورت پیش‌فرض داده را **مستقیم از provider همان
BedWarsCore** می‌خواند؛ پس برای کار کردن تاج‌ها **هیچ فایلی از bedwars_v7 را
لازم نیست تغییر بدهی**. این سند فقط برای وقتی است که بخواهی دستور خودِ BedWars
هم تاج بسازد:

```
/bwhologram create kills      →  به‌جای هولوگرام متنی، تاجِ قرمز ساخته شود
```

## پچ اختیاری (۶ خط) — `BedWarsLobby/src/sergittos/bedwars/lobby/leaderboard/LeaderboardManager.php`

در تابع `spawn(string $stat)`، قبل از خط `BedWarsCore::getInstance()->getProvider()->getLeaderboard(...)` این را بگذار:

```php
// 👑 اگر پلاگین تاج‌ها نصب است، به‌جای هولوگرام متنی، تاج غول‌پیکر ساخته شود
if (\class_exists(\arvan\leaderboards\Main::class) && \in_array($stat, \arvan\leaderboards\CrownManager::CROWN_STATS, true)) {
    \arvan\leaderboards\Main::get()->crowns()->spawnAt($world, $pos, $stat);
    return;
}
```

> این بلوک با `class_exists` محافظت شده؛ اگر پلاگین تاج نصب نباشد، **هیچ تغییری در
> رفتار قبلی BedWars پیش نمی‌آید** — نه خطا، نه کرش، نه لیدربورد تکراری.

## چرا این روش امن است؟

| نکته | دلیل |
| --- | --- |
| هیچ کلاس/فایل BedWarsCore بازنویسی نمی‌شود | تاج‌ها در پلاگین جدا ثبت می‌شوند؛ Customies هم فقط شناسهٔ تازه ثبت می‌کند |
| شناسه‌ها جدا هستند (`arvan:giant_crown_*`) | با `PlayBedwarsEntity` و بقیهٔ موجودیت‌های BedWars تداخل ندارند |
| دسته‌های مجاز همان ۸ دستهٔ provider است | `kills, wins, beds_broken, final_kills, deaths, level, coins, win_streak` |
| داده async خوانده می‌شود | نام‌تگ فقط داخل callback ست می‌شود؛ اگر NPC در این فاصله حذف شود، `isClosed()` جلوی خطا را می‌گیرد |
| در نبود BedWarsCore | بی‌صدا روی `stats.yml` خودِ پلاگین سوییچ می‌کند |

## اگر دو لیدربورد تکراری دیدی

فقط یکی از این دو کار را انجام بده:
1. تاج را با `/lb crown <stat>` بساز (و `/bwhologram create <stat>` را نزن)، یا
2. پچ بالا را بگذار و `/bwhologram` بزن (پلاگین تاج باید نصب باشد).

پاک‌کردن همهٔ تاج‌ها: `/lb crown remove 30` — و پاک‌کردن هولوگرام‌های متنی: `/bwhologram remove <stat>`.
