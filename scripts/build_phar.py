#!/usr/bin/env python3
"""
Builds a 100% PHP ext/phar compliant PHAR archive (AntiAdvertising.phar)
for PocketMine-MP API 5.
"""

from __future__ import annotations
import hashlib
import os
import struct
import time
import zlib
from pathlib import Path


def collect_plugin_files(root: Path) -> list[tuple[str, bytes, int]]:
    """
    Collects plugin.yml, resources/**, and src/** in deterministic order.
    Returns list of (relative_posix_path, raw_bytes, mtime).
    """
    entries: list[tuple[str, bytes, int]] = []

    plugin_yml = root / "plugin.yml"
    if not plugin_yml.is_file():
        raise FileNotFoundError("plugin.yml not found in repository root")

    entries.append(("plugin.yml", plugin_yml.read_bytes(), int(plugin_yml.stat().st_mtime)))

    for folder_name in ("resources", "src"):
        folder = root / folder_name
        if not folder.is_dir():
            continue
        for path in sorted(folder.rglob("*")):
            if path.is_file():
                rel_path = path.relative_to(root).as_posix()
                entries.append((rel_path, path.read_bytes(), int(path.stat().st_mtime)))

    return entries


def build_phar(root: Path, output_path: Path, alias: str = "AntiAdvertising.phar") -> None:
    files = collect_plugin_files(root)

    # Standard PocketMine-MP PHAR stub
    stub = b"<?php __HALT_COMPILER(); ?>\r\n"

    # Build per-file manifest entries and concatenate file contents
    manifest_entries = bytearray()
    file_data_blob = bytearray()

    for rel_path, data, mtime in files:
        name_bytes = rel_path.encode("utf-8")
        uncompressed_size = len(data)
        compressed_size = uncompressed_size
        crc32_val = zlib.crc32(data) & 0xFFFFFFFF
        # 0x000001B6 = chmod 0666, uncompressed
        file_flags = 0x000001B6
        metadata_len = 0

        entry = struct.pack(
            f"<I{len(name_bytes)}sIIIIII",
            len(name_bytes),
            name_bytes,
            uncompressed_size,
            mtime,
            compressed_size,
            crc32_val,
            file_flags,
            metadata_len,
        )
        manifest_entries.extend(entry)
        file_data_blob.extend(data)

    alias_bytes = alias.encode("utf-8")
    num_files = len(files)
    # API version 1.1.0 -> 0x11 0x00
    api_version = b"\x11\x00"
    # PHAR_HDR_SIGNATURE = 0x00010000
    global_flags = 0x00010000
    phar_metadata_len = 0

    manifest_header = struct.pack(
        f"<I2sII{len(alias_bytes)}sI",
        num_files,
        api_version,
        global_flags,
        len(alias_bytes),
        alias_bytes,
        phar_metadata_len,
    )

    manifest_body = bytes(manifest_header) + bytes(manifest_entries)
    manifest_len = len(manifest_body)

    unsigned_phar = (
        stub
        + struct.pack("<I", manifest_len)
        + manifest_body
        + bytes(file_data_blob)
    )

    # Compute SHA-1 signature (PHAR_SIG_SHA1 = 0x0002)
    sha1_digest = hashlib.sha1(unsigned_phar).digest()
    signature_footer = sha1_digest + struct.pack("<I4s", 0x0002, b"GBMB")

    full_phar = unsigned_phar + signature_footer
    output_path.write_bytes(full_phar)
    print(f"[OK] Built {output_path.name} ({len(full_phar)} bytes, {num_files} files)")


def verify_phar(phar_path: Path) -> None:
    raw = phar_path.read_bytes()
    assert raw.endswith(b"GBMB"), "Missing GBMB signature magic"
    sig_flag = struct.unpack("<I", raw[-8:-4])[0]
    assert sig_flag == 0x0002, f"Unexpected signature flag: {sig_flag}"
    expected_sha1 = raw[-28:-8]
    actual_sha1 = hashlib.sha1(raw[:-28]).digest()
    assert expected_sha1 == actual_sha1, "SHA-1 signature mismatch"

    halt_marker = b"__HALT_COMPILER(); ?>\r\n"
    idx = raw.find(halt_marker)
    assert idx != -1, "Stub halt marker not found"
    pos = idx + len(halt_marker)

    manifest_len = struct.unpack("<I", raw[pos:pos + 4])[0]
    pos += 4
    manifest_end = pos + manifest_len

    num_files = struct.unpack("<I", raw[pos:pos + 4])[0]
    pos += 4
    api_ver = raw[pos:pos + 2]
    assert api_ver == b"\x11\x00", f"Invalid API version bytes: {api_ver!r}"
    pos += 2
    global_flags = struct.unpack("<I", raw[pos:pos + 4])[0]
    assert global_flags & 0x00010000, "Signature flag not set in global flags"
    pos += 4
    alias_len = struct.unpack("<I", raw[pos:pos + 4])[0]
    pos += 4 + alias_len
    meta_len = struct.unpack("<I", raw[pos:pos + 4])[0]
    pos += 4 + meta_len

    file_records = []
    for _ in range(num_files):
        fn_len = struct.unpack("<I", raw[pos:pos + 4])[0]
        pos += 4
        fn = raw[pos:pos + fn_len].decode("utf-8")
        pos += fn_len
        usize, mtime, csize, crc, fflags, fmeta_len = struct.unpack("<IIIIII", raw[pos:pos + 24])
        pos += 24 + fmeta_len
        file_records.append((fn, usize, csize, crc))

    assert pos == manifest_end, f"Manifest end mismatch: {pos} != {manifest_end}"

    data_pos = manifest_end
    for fn, usize, csize, crc in file_records:
        chunk = raw[data_pos:data_pos + csize]
        assert len(chunk) == usize, f"Size mismatch for {fn}"
        assert (zlib.crc32(chunk) & 0xFFFFFFFF) == crc, f"CRC32 mismatch for {fn}"
        data_pos += csize
        print(f"  - Verified {fn} ({usize} bytes, CRC32={crc:#010x})")

    assert data_pos == len(raw) - 28, "Data end does not match signature start"
    print("[OK] PHAR binary verification passed 100%!")


if __name__ == "__main__":
    repo_root = Path(__file__).resolve().parent.parent
    out_phar = repo_root / "AntiAdvertising.phar"
    build_phar(repo_root, out_phar)
    verify_phar(out_phar)
