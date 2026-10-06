# 📥 فایل‌های آمادهٔ دانلود (ArvanLeaderboards v1.0)

| فایل | حجم | توضیح |
| --- | --- | --- |
| `ArvanLeaderboard.mcpack` | ~۱۱۰KB | ریسورس‌پک کامل — بگذار روی سرور یا در بازی import کن |
| `ArvanLeaderboards_plugin.zip` | ~۱۱KB | پلاگین PocketMine 5 (پوشهٔ `ArvanLeaderboards`) |
| `ArvanLeaderboard_models.zip` | ~۱٫۳MB | مدل‌ها (bbmodel/geo/animation) + بافت + پیش‌نمایش‌ها + ابزار پایتون |
| `ArvanLeaderboard_RP.zip` | ~۱۱۰KB | همان ریسورس‌پک به‌صورت پوشهٔ باز |
| `preview_hero.png` | ~۳۹۰KB | تصویر جمع‌بندی هر چهار مدل |

نصب سریع:

1. `ArvanLeaderboards_plugin.zip` را در پوشهٔ `plugins/` سرور باز کن (نیازمند پلاگین `Customies`).
2. ریسورس‌پک را در `resource_packs` سرور بگذار یا با `ArvanPackGuard` اجباری کن.
3. در بازی: `/lb spawn top` — و برای سکو/هولوگرام/تخت: `podium` / `holo` / `bed`.

ساخت دوبارهٔ همه‌چیز:

```bash
pip install pillow
python3 generate.py     # مدل‌ها + ریسورس‌پک + پیش‌نمایش‌ها
python3 package.py      # بسته‌بندی zipهای بالا
```
