#!/usr/bin/env python3
"""Protect a Bedrock resource pack: obfuscate file names, minify JSON, watermark textures, AES-256-CFB8 encrypt.

usage: protect_pack.py <pack_dir> <out.zip> [key]
Writes <out.zip> and <out.zip>.key (PocketMine-MP reads the key from that file automatically).
Verify a watermark:  protect_pack.py --check <texture.png>
"""
import hashlib, io, json, os, re, secrets, string, sys, zipfile
from PIL import Image
from cryptography.hazmat.primitives.ciphers import Cipher, algorithms, modes

MARK = "(c) ArvanGaming - stolen copy"
ALNUM = string.ascii_letters + string.digits


def rkey():
    return "".join(secrets.choice(ALNUM) for _ in range(32))


def enc(data, key):
    k = key.encode()
    e = Cipher(algorithms.AES(k), modes.CFB8(k[:16])).encryptor()
    return e.update(data) + e.finalize()


def watermark(png):
    """hide MARK in the LSB of the blue channel of opaque pixels (invisible)."""
    im = Image.open(io.BytesIO(png)).convert("RGBA")
    bits = "".join(f"{b:08b}" for b in (MARK + "\0").encode())
    px = im.load(); i = 0
    for y in range(im.height):
        for x in range(im.width):
            if i >= len(bits):
                break
            r, g, b, a = px[x, y]
            if a == 255:
                px[x, y] = (r, g, (b & ~1) | int(bits[i]), a); i += 1
    out = io.BytesIO(); im.save(out, "PNG", optimize=True)
    return out.getvalue()


def check(path):
    im = Image.open(path).convert("RGBA"); px = im.load(); bits = ""
    for y in range(im.height):
        for x in range(im.width):
            if px[x, y][3] == 255:
                bits += str(px[x, y][2] & 1)
    data = bytes(int(bits[i:i + 8], 2) for i in range(0, len(bits) - 7, 8))
    s = data.split(b"\0")[0][:64].decode(errors="replace")
    print("WATERMARK FOUND:" if s.startswith("(c) ArvanGaming") else "no watermark:", s)


def protect(src, out, key=None):
    key = key or rkey()
    files = {}
    for root, _, fs in os.walk(src):
        for f in fs:
            p = os.path.relpath(os.path.join(root, f), src).replace(os.sep, "/")
            files[p] = open(os.path.join(root, f), "rb").read()
    manifest = json.loads(files["manifest.json"]); pid = manifest["header"]["uuid"]

    # 1) obfuscate names (entity / model / animation / render controller / texture files)
    ren = {}
    for p in files:
        d, f = os.path.split(p)
        if d in ("entity", "models/entity", "animations", "render_controllers", "textures/entity"):
            ren[p] = f"{d}/{hashlib.sha1((pid + p).encode()).hexdigest()[:12]}{os.path.splitext(f)[1]}"
    texmap = {o[:-4]: n[:-4] for o, n in ren.items() if o.startswith("textures/") and o.endswith(".png")}
    new = {}
    for p, data in files.items():
        if p.endswith(".json"):
            j = json.loads(data)
            s = json.dumps(j, separators=(",", ":"))
            for o, n in texmap.items():
                s = s.replace(f'"{o}"', f'"{n}"')
            data = s.encode()
        elif p.endswith(".png") and p != "pack_icon.png":
            data = watermark(data)
        new[ren.get(p, p)] = data

    # 2) encrypt
    plain = {"manifest.json", "pack_icon.png"}
    content, z = [], zipfile.ZipFile(out, "w", zipfile.ZIP_DEFLATED)
    for p, data in sorted(new.items()):
        if p in plain:
            z.writestr(p, data); content.append({"path": p}); continue
        k = rkey(); z.writestr(p, enc(data, k)); content.append({"path": p, "key": k})
    body = enc(json.dumps({"content": content}, separators=(",", ":")).encode(), key)
    head = bytearray(256)
    head[4:8] = (0x9BCFB9FC).to_bytes(4, "little")
    head[0x10] = len(pid); head[0x11:0x11 + len(pid)] = pid.encode()
    z.writestr("contents.json", bytes(head) + body); z.close()
    open(out + ".key", "w").write(key)
    return key


if __name__ == "__main__":
    if sys.argv[1] == "--check":
        check(sys.argv[2])
    else:
        k = protect(sys.argv[1], sys.argv[2], sys.argv[3] if len(sys.argv) > 3 else None)
        print("encrypted ->", sys.argv[2], "key file ->", sys.argv[2] + ".key")
