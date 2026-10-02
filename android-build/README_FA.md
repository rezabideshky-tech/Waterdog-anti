# ساخت APK با GitHub Actions

این پروژه فقط به‌عنوان لایهٔ ساخت Gradle برای سورس کلاسیک AIDE وجود دارد؛ فایل‌های جاوا، مانیفست و منابع از `../minecraft-academy-aide` خوانده می‌شوند تا یک نسخهٔ جداگانه از اپ نگه‌داری نشود.

با Push تغییرات اپ به شاخهٔ `arena/01a0fbb8-waterdog-anti`، workflow زیر اجرا می‌شود:

`.github/workflows/build-android-apk.yml`

پس از موفقیت، از صفحهٔ **Actions** در GitHub وارد اجرای **Build Minecraft Academy APK** شو و artifact با نام `MinecraftAcademy-debug-apk` را دانلود کن. داخل فایل دانلودشده، `app-debug.apk` قرار دارد و برای نصب آزمایشی با کلید debug امضا شده است.

برای ساخت روی رایانه‌ای که JDK 17 و Android SDK نصب دارد:

```bash
gradle --no-daemon -p android-build :app:assembleDebug
```

خروجی:

`android-build/app/build/outputs/apk/debug/app-debug.apk`
