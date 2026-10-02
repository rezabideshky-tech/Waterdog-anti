# ایده‌های پلاگین برای شبکه Bedrock (PocketMine-MP + WaterdogPE)

> این سند مخصوص شبکه‌ای است که با **WaterdogPE** (پروکسی) و سرورهای **PocketMine-MP** پشت آن کار می‌کند،
> روی **Java 21** و بازه نسخه‌ای **۱.۲۰ تا ۱.۲۶.۳۰** کلاینت‌ها.

---

## ۰) اول یک واقعیت مهم که همه‌چیز را ساده می‌کند

آخرین نسخه WaterdogPE (نسخه `2.0.4-SNAPSHOT`، همان چیزی که این ریپو با آن بیلد می‌شود) از
**پروتکل ۳۱۳ (نسخه ۱.۸) تا ۲۱۹۳ (نسخه ۲۶.۵۰)** را پشتیبانی می‌کند؛ یعنی نسخه‌هایی که شما می‌خواهید:

| نسخه کلاینت | پروتکل | در WaterdogPE |
| --- | --- | --- |
| ۱.۲۰.۰ | 589 | ✅ |
| ۱.۲۱.۰ | 685 | ✅ |
| ۱.۲۱.۸۰ | 800 | ✅ |
| **۱.۲۶.۳۰** | **1001** | ✅ (`MINECRAFT_PE_1_26_30`, کدک `Bedrock_v1001`) |
| ۲۶.۵۰ (آخرین) | 2193 | ✅ |

نتیجه: **یک پروکسی، همه نسخه‌ها.** لازم نیست برای هر نسخه سرور جدا بسازید؛ پروکسی پکت‌ها را
بین نسخه‌ها ترجمه (rewrite) می‌کند. پس پلاگین باید *نسخه‌آگنوستیک* نوشته شود:

- فقط از API خود WaterdogPE و کلاس‌های `org.cloudburstmc.protocol.*` استفاده کنید،
- هرگز روی شماره پروتکل هارد‌کد تصمیم نگیرید (به‌جایش `player.getProtocol()` را با
  `ProtocolVersion.MINECRAFT_PE_1_20_0.isBeforeOrEqual(...)` مقایسه کنید).

### آنچه پروکسی **از قبل** دارد (دوباره نسازید)
WaterdogPE خودش این‌ها را دارد؛ در `config.yml` پروکسی قابل تنظیم‌اند:

- `connection_throttle` و `login_throttle` (تعداد اتصال/لاگین در هر بازه از یک IP)
- `enable_cookies` (کوکی RakNet، ضد اسپوف IP/ضد بات پایه)
- `login_timeout`، `error_timeout` (بلاک خودکار IP در خطا)
- `DefaultReconnectHandler` / `DefaultJoinHandler` (fallback و انتخاب سرور)
- `max_player_count`، مدیریت ریسورس‌پک، تزریق دستورات، Query و NetherNet

هر ایده‌ای که پایین می‌آید، چیزی است که **بالای** این‌ها ارزش افزوده می‌سازد.

---

## ۱) فهرست کوتاه ایده‌ها (به ترتیب اولویت)

| # | ایده | چه‌کار می‌کند | API کلیدی در WaterdogPE 2.x | سختی | ارزش |
| --- | --- | --- | --- | --- | --- |
| ۱ | **WaterdogAnti** (همین ریپو) | ضد بات، ضد اسپم چت/دستور، محدودیت پکت، fallback لابی | `PlayerAuthenticatedEvent`, `PlayerChatEvent`, `DispatchCommandEvent`, `PluginPacketHandler`, `IReconnectHandler` | متوسط | 🔥🔥🔥 |
| ۲ | **AntiBot Challenge** | قبل از ورود، کد/تایتل چالشی؛ بازیکن واقعی رد می‌شود، بات می‌ماند | `PlayerLoginEvent` (async)، `ProxiedPlayer.sendTitle` | متوسط | 🔥🔥🔥 |
| ۳ | **PacketFirewall** | فیلتر پکت‌های خطرناک/کراش‌زن بین نسخه‌ها + drop الگوهای اکسپلویت | `PluginPacketHandler` + `Signals.CANCEL` | پیشرفته | 🔥🔥🔥 |
| ۴ | **Forensics / Replay** | ذخیره آخرین N پکت مشکوک هر بازیکن برای بررسی تقلب | `PluginPacketHandler` + فایل JSON | متوسط | 🔥🔥 |
| ۵ | **Smart LoadBalancer** | توزیع بازیکن بین لابی‌ها بر اساس جمعیت/سلامت/همان سرور قبلی | `IJoinHandler`, `ServerInfo.getPlayers()` | ساده | 🔥🔥🔥 |
| ۶ | **Session Resumer** | بعد از کرش، بازیکن به همان سرور و همان مکان برگردد | `IReconnectHandler.getFallbackServer` | متوسط | 🔥🔥 |
| ۷ | **Join Queue** | صف ورود برای لابی پر/رویداد با پیام موقعیت در صف | `PlayerLoginEvent` + `scheduler` | متوسط | 🔥🔥 |
| ۸ | **Analytics + Dashboard** | متریک جمعیت/پینگ/ورود روی یک وب‌سرویس کوچک | `scheduler`, `ProxyPingEvent` | متوسط | 🔥🔥 |
| ۹ | **Custom MOTD/Ping Guard** | MOTD پویا، نمایش وضعیت سرور، فیلتر پینگ بات‌ها | `ProxyPingEvent`, `ProxyQueryEvent` | ساده | 🔥 |
| ۱۰ | **Rank/NameTag/Skin** | رنک و رنگ نام، تغییر اسکین/کیپ از پروکسی | `PreClientDataSetEvent`, `player.getRewriteData()` | متوسط | 🔥🔥 |
| ۱۱ | **Cross-server Bridge** | چت سراسری، `/msg`، تلپورت اجتماعی، دوستان | StarGate یا Redis + رویدادهای پروکسی | پیشرفته | 🔥🔥🔥 |
| ۱۲ | **Admin Security** | ۲FA/IP-lock برای ادمین‌ها، لاگ ورود ادمین | `PlayerAuthenticatedEvent`, PlayerStore | متوسط | 🔥🔥 |
| ۱۳ | **Reward/Event Scheduler** | رویداد زمان‌بندی‌شده، جوایز ورود روزانه، اعلان سراسری | `scheduler` + دستورات | ساده | 🔥 |
| ۱۴ | **Resource Pack Manager** | توزیع/ورژن‌بندی/اجبار پک‌ها بدون ری‌استارت کلاینت | `PackManager`, `PlayerResourcePackApplyEvent` | پیشرفته | 🔥 |
| ۱۵ | **Anti-VPN / Geo-Fence** | تشخیص VPN/داatacenter و محدودسازی کشورها | `PlayerAuthenticatedEvent` + سرویس بیرونی | پیشرفته | 🔥🔥 |

---

## ۲) ایده شماره ۱ — WaterdogAnti (این ریپو، عملاً ساخته شد ✅)

**مسئله:** سرورهای Bedrock بیشتر از هر چیزی با «بات/فلوود اتصال»، «اسپم چت»، «فلود پکت» و
«کرش سرور وسط بازی» درگیرند. سمت PocketMine این‌ها را دیرهنگام می‌بینید (وقتی منابع مصرف شده)، ولی
پروکسی **اولین نقطه تماس** است و می‌تواند ارزان‌تر جلوی همه را بگیرد.

**چهار ماژول پیاده‌شده:**

1. **JoinGuard** – روی `PlayerAuthenticatedEvent` (قبل از ساخته‌شدن بازیکن):
   - اجبار اکانت Xbox (رد کردن اکانت مهمان/کرک) ← مؤثرترین سلاح ضد بات در Bedrock
   - بررسی regex نام، نام‌های ممنوع، وایت‌لیست
   - محدودسازی بازه پروتکل (مثلاً حداقل ۱.۲۰)
   - تشخیص نام تکراری/جانشین (با مقایسه XUID، نه نام)
   - قفل نام به XUID در فایل `players.db` (تشخیص تعویض نام/اسپوف)
   - شمردن ورود هر IP در بازه زمانی و **بلاک واقعی IP** با `SecurityManager.blockAddress(...)`
   - تشخیص «موج ورود» (Wave) و بلاک IP‌های پرتکرار در حمله
2. **ChatGuard** – روی `PlayerChatEvent` و `DispatchCommandEvent`: محدودیت نرخ، ضد پیام تکراری،
   فیلتر کلمات/لینک/IP، سایلنت خودکار، شمارش تخلف.
3. **PacketGuard** – روی `PluginPacketHandler` (دیدن همه پکت‌های ورودی هر بازیکن): سقف نرخ
   برای پکت‌های پرخطر (`PlayerAuthInputPacket`, `InventoryTransactionPacket`, `SubChunkRequestPacket`, ...)،
   drop پکت اضافه، و در حالت تهاجمی کیک/بلاک IP.
4. **LobbyFallback** – روی `IReconnectHandler`: اگر سروری کرش کرد/کیک شد/تایم‌اوت داد، بازیکن
   به **سالم‌ترین لابی** (کمترین بازیکن) منتقل می‌شود، نه به بیرون شبکه. بن و کیک‌های اداری رد می‌شوند.

**چرا جواب می‌دهد:** هیچ تغییری در سرورهای PocketMine لازم نیست، همه‌چیز در پروکسی متمرکز
است و برای هر ۱۲۰۰ بازیکن هم یک نقطه تصمیم‌گیری دارد. تنظیمات کامل در `config.yml` و مدیریت با `/wda`.

**کارهای بعدی روی همین پلاگین (نسخه ۱.۱ پیشنهادی):**
- Challenge برای ورود (ایده ۲) به‌عنوان ماژول پنجم
- ذخیره پکت‌های مشکوک (ایده ۴)
- Webhook دیسکورد برای هشدارها (`Alert` + HTTP بدون کتابخانه اضافه = `java.net.http`)

---

## ۳) ایده شماره ۲ — AntiBot Challenge (بزرگ‌ترین برد بعدی)

**مسئله:** بعضی بات‌ها Xbox-authed هم هستند (اکانت‌های ساخته‌شده با API). پس فقط `require-xbox-auth`
کافی نیست.

**راه‌حل:** در `PlayerLoginEvent` (که **async** است و پروکسی تا ۶۰ ثانیه منتظر تمام‌شدنش می‌ماند)
بازیکن را در وضعیت «در انتظار تأیید» نگه دارید:
- یک کد ۴ رقمی در تایتل/اکشن‌بار نشان دهید + دکمه‌ی «من ربات نیستم» (فرم `ModalFormRequestPacket`)
- اگر کد درست وارد شد → اجازه ورود؛ وگرنه کیک.
- برای بازیکنان شناخته‌شده (XUID در `players.db`) هیچ چالشی نشان ندهید (تجربه کاربری سالم).

**چرا مهم است:** هزینه هر بات را از «یک اتصال» به «یک تعامل انسانی» می‌برد. کد نمونه:

```java
this.getProxy().getEventManager().subscribe(PlayerLoginEvent.class, event -> {
    ProxiedPlayer player = event.getPlayer();
    if (this.store.isKnown(player.getXuid())) return;      // بازیکن قدیمی
    if (!this.challenge.verify(player)) {                  // منتظر تایید می‌ماند (async)
        event.setCancelReason("§cVerification failed / timed out.");
        event.setCancelled(true);
    }
});
```

> نکته: `PlayerLoginEvent` قطعاً async است؛ پس اگر بیش از حد طول بکشید پروکسی خودش تایم‌اوت می‌کند
> (پیش‌فرض ۶۰ ثانیه در `ProxiedPlayer.LOGIN_EVENT_TIMEOUT_SECONDS`). کد را روی
> `getProxy().getEventManager().callEvent(...)` + یک `CompletableFuture` داخلی بسازید.

---

## ۴) ایده شماره ۳ — PacketFirewall (محافظ پکت بین‌نسخه‌ای)

**مسئله:** وقتی یک پروکسی همه‌ی نسخه‌ها (۱.۲۰ تا ۲۶.۵۰) را می‌پذیرد، گاهی کلاینت‌های قدیمی یا
پکت‌های دستی‌ساخته می‌توانند مسیرهای کدِ rewrite را به خطا بیاندازند (یا سرور PocketMine قدیمی
پکت نسخه جدید را نمی‌فهمد).

**راه‌حل:** `PluginPacketHandler` را به بازیکن بچسبانید و:

- پکت‌هایی که در نسخه‌ی بازیکن وجود ندارند، drop کنید (`ProtocolVersion` + `isBefore`)
- پکت‌هایی که مقدارهای غیرممکن دارند (طول رشته‌ی بسیار زیاد، مختصات NaN، InventoryTransaction
  با تعداد اسلات خارج از محدوده) را قبل از رسیدن به سرور حذف کنید
- پکت‌های زیر یک آستانه اندازه (`packet.getSize()`) را لاگ کنید

```java
public PacketSignal handlePacket(BedrockPacket packet, PacketDirection dir) {
    if (dir != PacketDirection.SERVER_BOUND) return PacketSignal.UNHANDLED;
    if (!isAllowedFor(player.getProtocol(), packet)) return Signals.CANCEL;  // drop
    ...
}
```

**هشدار مهم:** فقط پکت‌هایی را فیلتر کنید که مطمئنید بی‌خطرند؛ `CANCEL` بی‌دلیل = دی‌سینک/کیک بازیکن واقعی.
همیشه ابتدا در حالت لاگ اجرا کنید.

---

## ۵) ایده شماره ۵ — لودبالانسر هوشمند (۱۵ دقیقه کار، اثر فوری)

```java
public class SmartJoinHandler implements IJoinHandler {
    @Override
    public ServerInfo determineServer(ProxiedPlayer player) {
        ServerInfo best = null; int min = Integer.MAX_VALUE;
        for (String name : lobbyNames) {
            ServerInfo server = player.getProxy().getServerInfo(name);
            if (server == null) continue;
            int players = server.getPlayers().size();
            if (players < min) { min = players; best = server; }
        }
        return best;
    }
}
```
ثبت: `getProxy().setJoinHandler(new SmartJoinHandler())` — همین روش برای «انتخاب لابی بر اساس
آخرین سرور بازیکن» (ذخیره در `players.db`) هم استفاده می‌شود و از لود نامتوازن لابی‌ها جلوگیری می‌کند.

---

## ۶) ایده شماره ۶ — Session Resumer (تجربه کاربری حرفه‌ای)

در `IReconnectHandler`، قبل از fallback به لابی، اول **همان سرور قبلی** را دوباره امتحان کنید
(سرور معمولاً ۵–۱۰ ثانیه بعد از ری‌استارت بالا می‌آید):

```java
@Override
public ServerInfo getFallbackServer(ProxiedPlayer player, ServerInfo old, ReconnectReason reason, String msg) {
    ServerInfo again = player.getProxy().getServerInfo(old.getServerName());
    if (again != null && this.isHealthy(again)) {        // کش سلامت، بدون DNS/دیتابیس
        this.scheduleDelayedRejoin(player, again, 20);   // ۱ ثانیه بعد
        return null;                                     // فعلاً کیک نشود؟
    }
    return this.pickLobby(player, old);
}
```
> در عمل `getFallbackServer` باید سریع برگردد؛ برای «تلاش مجدد» از `scheduler` استفاده کنید و
> نتیجه را در حافظه کش کنید (الگوی خودِ پروکسی: `ServerInfo.getResolvedAddress()` با TTL 30 ثانیه).

---

## ۷) ایده شماره ۱۱ — Cross-server Bridge (بیشترین ارزش تجاری)

Bedrock جای Velocity/BungeeCord-plugin-messaging را ندارد، ولی دو مسیر مطمئن هست:

- **StarGate** (`Alemiz112/StarGate`) — پروتکل ارتباطی پروکسی↔سرور (پورت جدا، پکت‌محور)
- **RedisBridge** — ثبت خودکار سرور + Pub/Sub برای چت سراسری/دیتای مشترک

با آن می‌توانید بسازید: چت سراسری بین لابی‌ها و مینی‌گیم‌ها، `/msg` و `/r`، لیست دوستان،
«پنجره انتقال» (بازیکن از منی‌گیم برمی‌گردد به همان لابی)، امتیاز/سکه سراسری، و رویدادهای شبکه‌ای.
سمت پروکسی: دستورات و روتینگ؛ سمت PocketMine: اقتصاد/دیتا (یا برعکس، بستگی به معماری شما).

---

## ۸) چه چیزهایی را در پروکسی **نسازید** (تله‌ها)

- ❌ **آنتی‌چت حرکتی/کمبات واقعی:** پروکسی دنیا و موجودیت‌ها را شبیه‌سازی نمی‌کند
  (`ServerInfo` فقط آدرس/بازیکن‌ها را دارد). Fly/Reach/Killaura واقعی باید سمت PocketMine باشد؛
  سمت پروکسی فقط «نرخ پکت» و «ناوردی‌های آشکار» را بگیرید.
- ❌ **تغییر دنیا/بلاک:** پروکسی محتوای `LevelChunkPacket` را بازنویسی می‌کند ولی مالک آن نیست.
- ❌ **هوک‌کردن کتابخانه‌های سمت سرور:** فقط چیزی که در classpath پروکسی است (`CloudburstMC Protocol`,
  Netty, Gson, SnakeYAML, Fastutil, Log4j) در دسترس پلاگین است؛ منبع دیگر را shade کنید.
- ❌ **کار سنگین در `IJoinHandler`/`IReconnectHandler`:** این‌ها روی مسیر اتصال اجرا می‌شوند
  (بدون DB/DNS/HTTP، فقط حافظه).

---

## ۹) نقشه راه پیشنهادی (۴ هفته)

| هفته | کار | نتیجه |
| --- | --- | --- |
| ۱ | همین پلاگین WaterdogAnti + تست `/wda status`، حالت `alert` برای پکت‌ها | جلوی بات/اسپم/فلود گرفته شد |
| ۲ | AntiBot Challenge (ایده ۲) + وبهوک هشدار دیسکورد | حمله‌های انسانی هم دفع می‌شود |
| ۳ | SmartJoinHandler + Session Resumer + بکاپ پیکربندی چند لابی | تجربه بعد از کرش نرم می‌شود |
| ۴ | Cross-server Bridge (چت سراسری + اقتصاد) یا Dashboard (ایده ۸) | رشد و ماندگاری بازیکن |

---

## ۱۰) ساخت و راه‌اندازی (چکیده)

```bash
# ساخت (نیاز: JDK 21 + Maven 3.9+)
mvn -B clean package            # خروجی: target/WaterdogAnti-1.0.0.jar
# نصب
cp target/WaterdogAnti-1.0.0.jar  /path/to/waterdog/plugins/
# سپس پروکسی را ری‌استارت کنید و در کنسول بزنید: /wda status
```

- در `config.yml` پروکسی، برای دیدن هشدارها به بازیکن‌ها:
  ```yaml
  player_permissions:
    AdminName:
      - waterdoganti.command
      - waterdoganti.alerts
      - waterdoganti.bypass
  ```
- ترتیب درست اجرا: **اول** جوین `alert` (فقط لاگ)، بعد از چند روز بررسی، `punish` را فعال کنید.
