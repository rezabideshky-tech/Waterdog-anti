# WaterdogAnti 🛡️

پلاگین محافظت سمت **پروکسی** برای WaterdogPE — مخصوص شبکه‌های Bedrock
(**PocketMine-MP / Nukkit / BDS** پشت پروکسی).

| | |
| --- | --- |
| پروکسی | WaterdogPE `2.0.4-SNAPSHOT` (API نسخه ۲) |
| نسخه‌های Bedrock | **۱.۲۰ → ۱.۲۶.۳۰ و بالاتر** (پروتکل ۵۸۹ تا ۲۱۹۳) |
| Java | ۲۱ (بیلد با `maven.compiler.release=21`، پروکسی خودش روی ۱۷+ اجرا می‌شود) |
| وابستگی | هیچ کتابخانه اضافه‌ای لازم نیست (فقط `provided` پروکسی) |

> این پلاگین جانشین پروژه قبلی «WaterdogLobbyFallback» است (کدی که با API قدیمی
> `ServerDisconnectEvent` نوشته شده بود و کامپایل نمی‌شد). همان قابلیت حالا درست و بر پایه
> `IReconnectHandler` نسخه ۲ پیاده‌سازی شده است.

---

## ماژول‌ها

| ماژول | چه‌کار می‌کند | هوک API |
| --- | --- | --- |
| **JoinGuard** | ضد بات و ضد فلود ورود، اجبار اکانت Xbox، بررسی نام/وایت‌لیست/بازه پروتکل، تشخیص نام تکراری و تعویض نام (قفل XUID↔نام)، بلاک واقعی IP | `PlayerAuthenticatedEvent` |
| **ChatGuard** | ضد اسپم چت و دستور، فیلتر کلمات/لینک/IP، سایلنت خودکار | `PlayerChatEvent`, `DispatchCommandEvent` |
| **PacketGuard** | سقف نرخ پکت به‌ازای هر نوع پکت برای هر بازیکن، drop پکت اضافه، کیک/بلاک در حالت تهاجمی | `PluginPacketHandler` |
| **LobbyFallback** | انتقال بازیکن به سالم‌ترین لابی وقتی سرور کرش/تایم‌اوت/کیک می‌دهد (بن‌ها رد می‌شوند) | `IReconnectHandler` |
| **Alerts** | هشدار به کنسول + ادمین‌ها + فایل `alerts.log` | — |
| **PlayerStore** | بانک `players.db` (XUID ↔ نام) برای تشخیص اسپوف | — |

---

## ساخت

### الف) با GitHub Actions (بدون نیاز به نصب چیزی روی سیستم شما)
هر بار که روی هر برنچی پوش کنید، ورک‌فلو `Build Plugin JAR` اجرا می‌شود:

1. تب **Actions** → آخرین اجرا → فایل `WaterdogAnti-jar` را دانلود کنید.
2. فایل `WaterdogAnti-1.0.0.jar` را در پوشه `plugins/` پروکسی کپی کنید.

### ب) روی سیستم خودتان

```bash
# پیش‌نیاز: JDK 21 و Maven 3.9+
mvn -B clean package
# خروجی: target/WaterdogAnti-1.0.0.jar
```

سپس:

```bash
cp target/WaterdogAnti-1.0.0.jar /path/to/waterdog/plugins/
# پروکسی را ری‌استارت کنید
```

در کنسول باید ببینید:

```
[WaterdogAnti] WaterdogAnti v1.0.0 enabled - join: true, chat: true, packets: true, fallback: true
```

---

## پیکربندی

فایل `plugins/waterdoganti/config.yml` در اولین اجرا ساخته می‌شود. مهم‌ترین کلیدها:

| کلید | پیش‌فرض | توضیح |
| --- | --- | --- |
| `join-guard.require-xbox-auth` | `true` | فقط اکانت‌های Xbox Live (اکانت مهمان/کرک رد می‌شود) |
| `join-guard.name-regex` | `^[A-Za-z0-9_ ]{3,16}$` | الگوی نام مجاز |
| `join-guard.max-joins-per-ip` | `4` | حداکثر ورود از یک IP در بازه `join-window-seconds` |
| `join-guard.block-duration-seconds` | `300` | مدت بلاک IP متخلف |
| `join-guard.protocol-guard-enabled` | `false` | محدودکردن بازه نسخه‌ها (`min-protocol`/`max-protocol`) |
| `join-guard.strict-name-binding` | `false` | اگر true شود، تعویض نام ممنوع می‌شود |
| `chat-guard.max-messages` | `6` | حداکثر پیام در `window-seconds` |
| `packet-guard.action` | `alert` | `alert` فقط هشدار، `kick` اخراج، `block` بلاک IP + اخراج |
| `packet-guard.limits` | فهرست | سقف پکت هر نوع (نام کلاس پکت Bedrock) |
| `lobby-fallback.lobby-servers` | `lobby1, lobby2` | اگر خالی باشد، ReconnectHandler پیش‌فرض پروکسی دست‌نخورده می‌ماند |
| `lobby-fallback.strategy` | `least-players` | `first` یا پخش بار بر اساس جمعیت |

> 💡 **مهم:** یک هفته اول `packet-guard.action: alert` بگذارید، `alerts.log` را ببینید و اگر
> هشدار اشتباهی نبود، روی `kick` بگذارید. آستانه‌ها به سخت‌افزار و مینی‌گیم‌های شما بستگی دارد.

---

## دستورات و پرمیشن‌ها

| دستور | کار |
| --- | --- |
| `/wda status` | وضعیت ماژول‌ها و شمارنده‌ها |
| `/wda info <player>` | XUID، پروتکل، دستگاه، امتیاز تخلف |
| `/wda reset <player>` | پاک‌کردن امتیاز تخلف |
| `/wda unblock <ip>` | رفع بلاک IP |
| `/wda whitelist <add\|remove\|list> [name]` | مدیریت وایت‌لیست |
| `/wda alerts` | روشن/خاموش کردن هشدار برای خودتان |
| `/wda reload` | بارگذاری مجدد `config.yml` |

| پرمیشن | پیش‌فرض | توضیح |
| --- | --- | --- |
| `waterdoganti.command` | فقط ادمین (در `player_permissions` پروکسی بدهید) | اجرای `/wda` |
| `waterdoganti.alerts` | — | دیدن هشدارها در چت |
| `waterdoganti.bypass` | — | رد شدن از همه بررسی‌ها |

نمونه در `config.yml` خود پروکسی:

```yaml
player_permissions:
  AdminName:
    - waterdoganti.command
    - waterdoganti.alerts
    - waterdoganti.bypass
```

---

## نکات عملیاتی

- **کجا نصب شود؟** فقط روی پروکسی (`plugins/`). روی سرورهای PocketMine چیزی نصب نمی‌شود.
- **چند نسخه؟** یک پروکسی، همه نسخه‌ها؛ بررسی‌ها بر اساس `player.getProtocol()` انجام می‌شود،
  نه شماره پروتکل هارد‌کد.
- **فایل‌های داده:** `plugins/waterdoganti/players.db` (XUID↔نام) و `alerts.log`.
  ۱۰۰ هزار رکورد ≈ چند مگابایت؛ روی هارد ذخیره می‌شود و هر ۶۰ ثانیه یک‌بار نوشته می‌شود.
- **تعامل با `reconnect_handler` پروکسی:** اگر `lobby-fallback.lobby-servers` پر باشد، این پلاگین
  ReconnectHandler پروکسی را جایگزین می‌کند (چون API اجازه فقط یک هندلر می‌دهد). برای برگشت به
  حالت پیش‌فرض، لیست لابی‌ها را خالی کنید و پروکسی را ری‌استارت کنید.
- **کارایی:** همه ماژول‌ها O(1) به‌ازای هر رویداد/پکت هستند و هیچ I/O روی مسیر پکت انجام نمی‌شود.

## عیب‌یابی

| نشانه | راه‌حل |
| --- | --- |
| پلاگین لود نمی‌شود | برنچ `main` را با آخرین GitHub Actions بیلد کنید؛ خطای کامپایل را در تب Actions ببینید |
| ادمین‌ها هم کیک می‌شوند | پرمیشن `waterdoganti.bypass` را بدهید یا نام را در `general.bypass-names` بگذارید |
| لابی‌ها بعد از کرش جایگزین نمی‌شوند | نام‌ها در `lobby-fallback.lobby-servers` باید **دقیقاً** با `serverList` پروکسی یکی باشند |
| بازیکن‌های واقعی هشدار پکت می‌گیرند | مقدار `packet-guard.limits` همان پکت را بالا ببرید یا `action: alert` بگذارید |
| پکت‌های یک مینی‌گیم خاص مشکل دارند | نام پکت را در لاگ می‌بینید؛ همان کلید را در `limits` تنظیم کنید |

---

## ساختار پروژه

```
src/main/java/dev/waterdog/anti/
├── WaterdogAnti.java          # کلاس اصلی (Plugin)، چرخه enable/disable/reload
├── AntiConfig.java            # خواندن امن config
├── ViolationManager.java      # امتیاز تخلف، هشدارها، alerts.log
├── PlayerStore.java           # players.db (قفل XUID↔نام)
├── command/AntiCommand.java   # /wda
└── module/
    ├── JoinGuard.java         # PlayerAuthenticatedEvent
    ├── ChatGuard.java         # PlayerChatEvent + DispatchCommandEvent
    ├── PacketGuard.java       # PluginPacketHandler
    └── LobbyFallback.java     # IReconnectHandler
```

سند ایده‌ها و نقشه راه توسعه: [`IDEAS.md`](IDEAS.md)
