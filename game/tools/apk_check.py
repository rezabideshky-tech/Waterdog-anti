#!/usr/bin/env python3
"""tools/apk_check.py — بررسی سالم‌بودن APK ساخته‌شده (محتوای بازی، فونت‌ها و آیکون‌ها)

اجرا: python3 tools/apk_check.py android/app/build/outputs/apk/debug/app-debug.apk
"""
import sys
import zipfile

NEED = [
    'AndroidManifest.xml',
    'classes.dex',
    'assets/public/index.html',
    'assets/public/js/main.js',
    'assets/public/js/game.js',
    'assets/public/manifest.webmanifest',
    'assets/public/assets/img/icon-192.png',
    'assets/public/assets/img/icon-512.png',
]
MIN_JS = 14
MIN_FONTS = 4
MIN_ICONS = 8


def main(path):
    names = zipfile.ZipFile(path).namelist()
    missing = [n for n in NEED if n not in names]
    js = [n for n in names if n.startswith('assets/public/js/')]
    fonts = [n for n in names if n.endswith('.woff2')]
    icons = [n for n in names if 'ic_launcher' in n]
    print(f'apk={path}')
    print(f'js={len(js)} fonts={len(fonts)} icons={len(icons)} total_entries={len(names)}')
    if missing:
        print('❌ فایل‌های گمشده: ' + ', '.join(missing))
        return 1
    if len(js) < MIN_JS:
        print(f'❌ فایل‌های جاوااسکریپت بازی ناقص است ({len(js)} < {MIN_JS})')
        return 1
    if len(fonts) < MIN_FONTS:
        print(f'❌ فونت‌های فارسی داخل APK نیستند ({len(fonts)} < {MIN_FONTS})')
        return 1
    if len(icons) < MIN_ICONS:
        print(f'❌ آیکون‌های لانچر ساخته نشده‌اند ({len(icons)} < {MIN_ICONS})')
        return 1
    print('✅ محتوای APK سالم است')
    return 0


if __name__ == '__main__':
    if len(sys.argv) < 2:
        print('استفاده: python3 tools/apk_check.py <فایل apk>')
        sys.exit(2)
    sys.exit(main(sys.argv[1]))
