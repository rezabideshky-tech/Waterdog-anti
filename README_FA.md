# WaterdogLobbyFallback

افزونهٔ پراکسی WaterdogPE برای انتقال بازیکن به لابی وقتی اتصالش به یک سرور پشت‌صحنه قطع شود یا انتقال به سرور مقصد شکست بخورد.

## نکتهٔ نسخه

- برای **WaterdogPE** و Java 21 ساخته می‌شود؛ این افزونه باید داخل پوشهٔ `plugins/` خود پراکسی نصب شود، نه پوشهٔ پلاگین‌های PocketMine.
- `WaterdogLobbyFallback` و `ArvanShield` هر دو با API رسمی `dev.waterdog.waterdogpe:waterdog:2.0.4-SNAPSHOT` ساخته می‌شوند. از build رسمی جدید WaterdogPE با همین API استفاده کن؛ نسخهٔ قدیمی `v2.0.3` برای API متریک ArvanShield مناسب نیست. [نسخه‌های رسمی WaterdogPE](https://github.com/WaterdogPE/WaterdogPE/releases)
- نام لابی‌ها باید دقیقاً با نام سرورها در بخش `servers` کانفیگ WaterdogPE یکی باشد.
- نسخهٔ Minecraft/Bedrock مثل 1.20 یا 1.26.30 را خود افزونه تعیین نمی‌کند؛ سازگاری پروتکل را نسخهٔ خود WaterdogPE و نرم‌افزار سرورهای backend تعیین می‌کند.

## نصب

1. مطمئن شو پراکسی با Java 21 اجرا می‌شود.
2. فایل `WaterdogLobbyFallback-1.0.0.jar` را در پوشهٔ `plugins/` پراکسی WaterdogPE قرار بده.
3. پراکسی را اجرا کن تا `plugins/waterdoglobbyfallback/config.yml` ساخته شود.
4. در `config.yml` نام لابی‌ها را مطابق نام سرورهای Waterdog تنظیم کن.
5. پراکسی را کامل راه‌اندازی مجدد کن و پیام `Lobby fallback enabled` را در کنسول بررسی کن.

برای ساخت دستی با Maven و JDK 21:

```bash
mvn -U clean package
```

خروجی:

```text
target/waterdog-lobby-fallback-1.0.0.jar
```

در GitHub Actions نیز با هر Push به شاخهٔ کاری، JAR به‌صورت Artifact با نام `WaterdogLobbyFallback-JAR` ساخته می‌شود.

## رفتار افزونه

- از API رسمی `IReconnectHandler` در WaterdogPE استفاده می‌کند؛ به رویداد حدسی `ServerDisconnectEvent` یا تأخیر زمان‌بندی‌شده وابسته نیست.
- لابی‌ها را به ترتیب کانفیگ امتحان می‌کند و هر لابی را در یک چرخه فقط یک بار امتحان می‌کند تا حلقهٔ انتقال ایجاد نشود.
- بعد از انتقال موفق، فهرست تلاش‌های قبلی بازنشانی می‌شود.
- اگر بازیکن در لابی باشد و انتقالش به یک backend شکست بخورد، به‌طور پیش‌فرض در همان لابی می‌ماند.
- اگر هیچ لابی ثبت‌نشده/قابل‌استفاده‌ای نمانده باشد، پیام کانفیگ‌شده را می‌فرستد و از تلاش بی‌نهایت جلوگیری می‌کند.
- اگر نام هیچ‌کدام از لابی‌های کانفیگ‌شده در WaterdogPE ثبت نشده باشد، رفتار reconnect قبلی پراکسی حفظ می‌شود.

## تنظیمات مهم

در `src/main/resources/config.yml`:

```yaml
lobby-servers:
  - lobby
  - lobby-2

fallback-on-transfer-failure: true
keep-current-lobby-on-transfer-failure: true
```

تنظیمات نهایی پس از اولین اجرا در پوشهٔ دادهٔ افزونه قرار می‌گیرند. `fallback-reasons` هم مشخص می‌کند برای کدام خطاهای WaterdogPE انتقال به لابی انجام شود.

## عیب‌یابی سریع

- اگر در کنسول `Lobby '...' is not configured` دیدی، نام را با کلید دقیق سرور در کانفیگ پراکسی تطبیق بده.
- اگر لابی در دسترس نیست، یک لابی دوم اضافه کن تا افزونه بتواند آن را هم امتحان کند.
- این افزونه اتصال نسخه‌های ناسازگار Bedrock را تبدیل یا آپدیت نمی‌کند؛ اول مطمئن شو خود WaterdogPE از پروتکل کلاینت و نسخهٔ backend پشتیبانی می‌کند.

---

## افزونهٔ مستقل آنتی‌بات: ArvanShield

در مسیر [`arvanshield/`](arvanshield/README.md) یک افزونهٔ جداگانهٔ Java 21 برای محافظه‌کاری در ورود و پایش تجمیعی ترافیک قرار دارد. این افزونه با `WaterdogLobbyFallback` یکی نیست و باید به‌صورت JAR مستقل نصب شود. حالت پیش‌فرض آن `observe` است و ورود بازیکن را رد نمی‌کند. گزینهٔ جلوگیری از ورود جدید هنگام فشار پایدار ترافیک، پیش‌فرض خاموش است و فقط در حالت `attack` و پس از تنظیم صریح مدیر فعال می‌شود.

```bash
mvn -f arvanshield/pom.xml clean verify
```

خروجی `arvanshield/target/arvanshield-1.1.0.jar` است. جزئیات نصب و تنظیمات در [راهنمای ArvanShield](arvanshield/README.md) آمده است. API عمومی WaterdogPE در این افزونه فقط آمار تجمیعی می‌دهد و hook لغو بستهٔ خام هر بازیکن ندارد؛ افزونه جایگزین فایروال یا محافظ DDoS حجیم UDP در لایهٔ میزبان نیست. ArvanShield سازگاری پروتکل Bedrock اضافه نمی‌کند و نسخهٔ کلاینت را سیگنال بات نمی‌داند.

---

## اپ همراه سرور آروان گیمینگ

پروژهٔ [اپ آروان گیمینگ](arvan-gaming-app/README_FA.md) برای سرور Minecraft Bedrock/PocketMine به‌صورت ماژول جدا ساخته شده است. صفحه‌ها و دکمه‌های انیمیشنی، راهنمای ورود، رویدادها، خبرها/نظرسنجی، پروفایل و انجمن را دارد. وضعیت آنلاین/تعداد بازیکنان فقط پس از پاسخ واقعی Bedrock ping نمایش داده می‌شود؛ تا وقتی آدرس/API رسمی تنظیم نشده باشد، داده‌ای جعل نمی‌شود.

پس از Push، در GitHub Actions artifact با نام `ArvanGaming-debug-apk` ساخته می‌شود. راهنمای دقیق نصب، فید HTTPS و نیازهای اتصال امن حساب در README اپ آمده است.
