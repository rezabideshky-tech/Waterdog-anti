#!/usr/bin/env python3
"""Tiny software renderer for .bbmodel files (static pose) -> PNG preview."""
import base64, io, json, math, sys
from PIL import Image, ImageDraw


def rot_matrix(rx, ry, rz):
    rx, ry, rz = map(math.radians, (rx, ry, rz))
    cx, sx, cy, sy, cz, sz = math.cos(rx), math.sin(rx), math.cos(ry), math.sin(ry), math.cos(rz), math.sin(rz)
    Rx = [[1, 0, 0], [0, cx, -sx], [0, sx, cx]]
    Ry = [[cy, 0, sy], [0, 1, 0], [-sy, 0, cy]]
    Rz = [[cz, -sz, 0], [sz, cz, 0], [0, 0, 1]]
    mm = lambda A, B: [[sum(A[i][k] * B[k][j] for k in range(3)) for j in range(3)] for i in range(3)]
    return mm(mm(Rz, Ry), Rx)  # Blockbench ZYX order


def apply(M, p, o):
    v = [p[i] - o[i] for i in range(3)]
    return [sum(M[i][k] * v[k] for k in range(3)) + o[i] for i in range(3)]


def render(path, out, yaw=-25, pitch=20, size=900):
    bb = json.load(open(path))
    tex = Image.open(io.BytesIO(base64.b64decode(bb["textures"][0]["source"].split(",", 1)[1]))).convert("RGBA")
    els = {e["uuid"]: e for e in bb["elements"]}
    parent_chain = {}

    def walk(nodes, chain):
        for n in nodes:
            if isinstance(n, dict):
                c = chain + [(n.get("rotation", [0, 0, 0]), n["origin"])]
                walk(n["children"], c)
            else:
                parent_chain[n] = chain
    walk(bb["outliner"], [])
    cam = rot_matrix(pitch, yaw, 0)
    quads = []
    for uid, e in els.items():
        f, t = e["from"], e["to"]
        x1, y1, z1 = f; x2, y2, z2 = t
        faces = {  # top-left, top-right, bottom-left corners ; normal
            "north": ((x2, y2, z1), (x1, y2, z1), (x2, y1, z1), (0, 0, -1)),
            "south": ((x1, y2, z2), (x2, y2, z2), (x1, y1, z2), (0, 0, 1)),
            "east": ((x2, y2, z2), (x2, y2, z1), (x2, y1, z2), (1, 0, 0)),
            "west": ((x1, y2, z1), (x1, y2, z2), (x1, y1, z1), (-1, 0, 0)),
            "up": ((x1, y2, z1), (x2, y2, z1), (x1, y2, z2), (0, 1, 0)),
            "down": ((x1, y1, z2), (x2, y1, z2), (x1, y1, z1), (0, -1, 0)),
        }

        def xf(p, isvec=False):
            o = [0, 0, 0] if isvec else None
            if "rotation" in e:
                p = apply(rot_matrix(*e["rotation"]), p, o or e["origin"])
            for r, org in reversed(parent_chain.get(uid, [])):
                p = apply(rot_matrix(*r), p, o or org)
            return apply(cam, p, [0, 0, 0])
        for name, (tl, tr, bl, nrm) in faces.items():
            n = xf(nrm, True)
            if n[2] > 0:  # camera looks toward +z ; cull back faces
                continue
            u1, v1, u2, v2 = e["faces"][name]["uv"]
            nu, nv = max(1, int(abs(u2 - u1))), max(1, int(abs(v2 - v1)))
            nu, nv = min(nu, 32), min(nv, 32)
            P0, PU, PV = xf(tl), xf(tr), xf(bl)
            du = [(PU[i] - P0[i]) / nu for i in range(3)]
            dv = [(PV[i] - P0[i]) / nv for i in range(3)]
            for a in range(nu):
                for b in range(nv):
                    tu = int(u1 + (u2 - u1) * (a + 0.5) / nu); tv = int(v1 + (v2 - v1) * (b + 0.5) / nv)
                    col = tex.getpixel((min(tu, tex.width - 1), min(tv, tex.height - 1)))
                    if col[3] < 128:
                        continue
                    c = [P0[i] + du[i] * a + dv[i] * b for i in range(3)]
                    pts = [c, [c[i] + du[i] for i in range(3)], [c[i] + du[i] + dv[i] for i in range(3)], [c[i] + dv[i] for i in range(3)]]
                    shade = 0.75 + 0.25 * max(0, -n[2]) + (0.08 if name == "up" else 0)
                    quads.append((sum(p[2] for p in pts) / 4, pts, tuple(min(255, int(col[i] * shade)) for i in range(3))))
    quads.sort(key=lambda q: -q[0])
    img = Image.new("RGB", (size, size), (28, 22, 40))
    d = ImageDraw.Draw(img)
    xs = [p[0] for q in quads for p in q[1]]; ys = [p[1] for q in quads for p in q[1]]
    sc = size * 0.9 / max(max(xs) - min(xs), max(ys) - min(ys))
    cxm, cym = (max(xs) + min(xs)) / 2, (max(ys) + min(ys)) / 2
    for _, pts, col in quads:
        d.polygon([(size / 2 - (p[0] - cxm) * sc, size / 2 - (p[1] - cym) * sc) for p in pts], fill=col)
    img.save(out)


if __name__ == "__main__":
    render(sys.argv[1], sys.argv[2], *(float(a) for a in sys.argv[3:]))
