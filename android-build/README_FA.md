# ساخت APK با GitHub Actions

این پوشه لایهٔ ساخت Gradle است؛ سورس AIDE هر اپ فقط یک نسخه دارد و از این مسیرها خوانده می‌شود:

- `:app` از `../minecraft-academy-aide`
- `:arvan-gaming` از `../arvan-gaming-app`

با Push تغییرات به شاخهٔ `arena/01a0fbb8-waterdog-anti`، workflow زیر هر دو اپ را می‌سازد:

`.github/workflows/build-android-apk.yml`

در **Actions → اجرای workflow → Artifacts**، APK آروان گیمینگ را با نام `ArvanGaming-debug-apk` دریافت کنید؛ فایل APK داخل ZIP، `arvan-gaming-debug.apk` است. Artifact اپ قدیمی نیز جداگانه با نام `MinecraftAcademy-debug-apk` حفظ شده است. هر دو برای نصب آزمایشی با کلید debug امضا می‌شوند.

برای ساخت دستی با JDK 17 و Android SDK:

```bash
gradle --no-daemon -p android-build :arvan-gaming:assembleDebug
gradle --no-daemon -p android-build :app:assembleDebug
```

مسیر خروجی آروان گیمینگ:

`android-build/arvan-gaming/build/outputs/apk/debug/arvan-gaming-debug.apk`
