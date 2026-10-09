#!/usr/bin/env python3
"""Checks the store short descriptions against the factory limits.

English short description: 80 characters (international market, factory markets/international.md).
Persian short description: 60 characters (Bazaar rule, factory LESSONS L28).
The listing marks the two lines with EN_SHORT: and FA_SHORT:.
Run:  python3 store/check_lengths.py
"""
import sys
from pathlib import Path

LISTING = Path(__file__).with_name("store_listing.md").read_text(encoding="utf-8")
LIMITS = {"EN_SHORT": 80, "FA_SHORT": 60}


def short_desc(marker: str) -> str:
    for line in LISTING.splitlines():
        if marker + ":" in line:
            return line.split(marker + ":", 1)[1].strip()
    return ""


def main() -> int:
    ok = True
    for marker, limit in LIMITS.items():
        text = short_desc(marker)
        n = len(text)
        good = bool(text) and n <= limit
        print(f"{marker}: {n}/{limit} characters -> {'ok' if good else 'TOO LONG OR MISSING'}")
        ok = ok and good
    return 0 if ok else 1


if __name__ == "__main__":
    sys.exit(main())
