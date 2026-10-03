# آروان پژواک — کلاینت صوتی Minecraft Bedrock

آروان پژواک یک کلاینت Android فارسی برای گفت‌وگوی صوتی نزدیک‌محور Bedrock است: اپ موبایل صدا را با WebRTC/LiveKit جابه‌جا می‌کند؛ افزونهٔ PocketMine هویت داخل بازی و موقعیت بازیکنان را به درگاه کنترل می‌فرستد؛ درگاه فقط صدای بازیکنان هم‌دنیا و نزدیک را به هم وصل می‌کند.

## ظاهر و تجربهٔ اپ

- برند مستقل **آروان پژواک** با طراحی روشن بنفش‌وسفید، کارت‌های گرد، سایهٔ کم و فاصله‌گذاری موبایل‌محور.
- صفحه‌های گفت‌وگو، بازیکنان نزدیک و تنظیمات؛ ورود صفحه‌ها، لمس دکمه‌ها و وضعیت اتصال انیمیشن دارند.
- اتصال با کد کوتاه‌عمر که بازیکن با `/pejvak code` داخل بازی می‌گیرد؛ رمز Microsoft/Xbox هرگز درخواست نمی‌شود.
- دکمهٔ push-to-talk با لمس ممتد، قطع/فعال‌کردن میکروفن، انتخاب خروجی صدا و دکمهٔ شناور روی Minecraft.
- صدای واقعی به وضعیت LiveKit وابسته است؛ رابط کاربری هنگام قطع ارتباط، سرور یا بازیکن ساختگی نمایش نمی‌دهد.

## اجزای لازم

```text
Minecraft Bedrock + PocketMine plugin
                │ HTTPS: one-time code + player position snapshots
                ▼
Arvan Pejvak gateway ───── WebRTC/Opus ───── Android clients
                │
                └── LiveKit SFU (self-hosted)
```

PocketMine نباید صدای خام را روی حلقهٔ تیک بازی جابه‌جا کند؛ به همین دلیل نصب فقط APK و پلاگین نیست. باید gateway و LiveKit را هم روی یک VPS/سرور قابل‌دسترسی از اینترنت راه‌اندازی کنی. LiveKit ترافیک صوتی رمزگذاری‌شدهٔ WebRTC را انجام می‌دهد؛ gateway کد یک‌بارمصرف می‌سازد، نشست می‌دهد و اشتراک ترک‌ها را بر اساس دنیا و فاصله محدود می‌کند.

## راه‌اندازی سرویس صوتی

1. روی VPS، Docker و Docker Compose نصب کن.
2. وارد `gateway/` شو، `.env.example` را به `.env` کپی کن و برای `CONTROL_SHARED_SECRET` و `LIVEKIT_API_SECRET` secret تصادفی و بلند بگذار. مقدار `LIVEKIT_API_SECRET` باید با secret داخل `livekit.yaml` **یکسان** باشد.
3. `LIVEKIT_WS_URL` را روی نشانی عمومی `wss://` تنظیم کن. نمونهٔ `Caddyfile.example` مسیرهای `/v1/*` و `/health` را با HTTPS به gateway و باقی مسیرها را به signaling سرور LiveKit می‌فرستد؛ نام دامنه را عوض کن و Caddy را روی خود VPS اجرا کن. پورت‌های داخلی `8787` و `7880` در Compose فقط روی loopback باز هستند.
4. در firewall، پورت‌های TCP `80/443` برای Caddy، TCP `7881` برای fallback صدای WebRTC و UDP `50000–50100` برای LiveKit را باز کن؛ TCP `7880` را عمومی نکن. `use_external_ip` به IP عمومی درست نیاز دارد.
5. اجرا:

```bash
cd arvan-pejvak/gateway
docker compose up -d --build
```

**پیش از اینترنتی‌کردن:** کلید `devkey` و secret نمونهٔ داخل `livekit.yaml` را عوض کن، TLS واقعی بگذار، پورت‌های UDP را تست کن و یک VPS را با دو گوشی staging کن. فایل `.env` را commit نکن.

## راه‌اندازی PocketMine

1. `php -d phar.readonly=0 pocketmine/build-phar.php` را برای ساخت PHAR اجرا کن، یا artifact `ArvanPejvak-PocketMine-PHAR` را از GitHub Actions بگیر. برای تست می‌توان پوشهٔ `pocketmine/` را مستقیماً در `plugins/` گذاشت.
2. در `plugins/ArvanPejvak/config.yml`، gateway HTTPS، secret مشترک و `server-id` یکتا را تنظیم کن. مقدار `shared-secret` باید عین `CONTROL_SHARED_SECRET` باشد.
3. PocketMine را restart کن و لاگ `Arvan Pejvak bridge enabled` را ببین.
4. بازیکن داخل بازی `/pejvak code` را اجرا می‌کند؛ کد را در اپ وارد می‌کند.

پلاگین هر ثانیه یک snapshot از XUID/UUID، نام، دنیا و مختصات بازیکنان می‌فرستد. درخواست HTTP در AsyncPool است تا کار شبکه‌ای تیک اصلی را متوقف نکند. نام دنیا پیش از ارسال hash می‌شود.

## ساخت APK

GitHub Actions workflow مستقل **Build Arvan Pejvak APK** artifact به نام `ArvanPejvak-debug-apk` می‌سازد. از Actions → اجرای موفق workflow → Artifacts آن را دانلود کن. ساخت دستی با JDK 17 و Android SDK:

```bash
gradle --no-daemon -p arvan-pejvak/android-build :app:assembleDebug
```

حداقل Android 8.0 (API 26) است. برای استفاده از میکروفن در پس‌زمینه، سرویس foreground و اعلان پایدار نمایش داده می‌شود. دکمهٔ شناور به مجوز «نمایش روی برنامه‌های دیگر» نیاز دارد. APK ساخت debug برای تست است؛ برای انتشار عمومی، signing key مستقل و فرایند release لازم است.

## قرارداد API

- `POST /v1/pocketmine/code` — صدور کد یک‌بارمصرف، فقط با Bearer secret پلاگین.
- `POST /v1/pocketmine/presence` — snapshot موقعیت‌ها، فقط با Bearer secret پلاگین.
- `POST /v1/mobile/exchange` — تبدیل کد به توکن کوتاه‌عمر LiveKit و کنترل.
- `GET /v1/mobile/nearby` — نام و فاصلهٔ بازیکنان هم‌دنیا در شعاع پیکربندی‌شده.
- `DELETE /v1/mobile/session` — پایان نشست اپ.

## وضعیت و محدودیت‌های این نسخه

این پیاده‌سازی **پایهٔ قابل‌توسعهٔ alpha** است و پیش از استفادهٔ عمومی به CI سبز، build موفق APK، نصب LiveKit روی VPS، تست PHAR روی نسخهٔ واقعی PocketMine، تست دو دستگاه و اندازه‌گیری تأخیر/مصرف نیاز دارد. کدها، حضور و نشست‌ها فعلاً در حافظهٔ یک gateway نگه‌داری می‌شوند؛ restart کدهای فعال را باطل می‌کند و چند replica بدون Redis هماهنگ نیستند. نسخهٔ PocketMine نیز در این محیط واقعی هنوز نصب و تست نشده است.
