/* tools/serve.mjs — سرور محلی بازی برای پیش‌نمایش و تست موبایل
   اجرا: node tools/serve.mjs [port]   (پیش‌فرض 8080، روی 0.0.0.0 گوش می‌دهد) */
import { createServer } from 'node:http';
import { readFile, stat } from 'node:fs/promises';
import { fileURLToPath } from 'node:url';
import { dirname, join, normalize, extname } from 'node:path';

const here = dirname(fileURLToPath(import.meta.url));
const root = join(here, '../www');
const port = Number(process.argv[2] || process.env.PORT || 8080);

const MIME = {
  '.html': 'text/html; charset=utf-8',
  '.js': 'text/javascript; charset=utf-8',
  '.mjs': 'text/javascript; charset=utf-8',
  '.css': 'text/css; charset=utf-8',
  '.json': 'application/json; charset=utf-8',
  '.webmanifest': 'application/manifest+json; charset=utf-8',
  '.png': 'image/png',
  '.jpg': 'image/jpeg',
  '.svg': 'image/svg+xml',
  '.ico': 'image/x-icon',
  '.woff2': 'font/woff2',
  '.woff': 'font/woff',
  '.txt': 'text/plain; charset=utf-8',
};

const server = createServer(async (req, res) => {
  try {
    const url = new URL(req.url, 'http://localhost');
    let path = decodeURIComponent(url.pathname);
    if (path.endsWith('/')) path += 'index.html';
    const full = join(root, normalize(path).replace(/^(\.\.[/\\])+/, ''));
    if (!full.startsWith(root)) { res.writeHead(403).end('forbidden'); return; }
    let info = await stat(full).catch(() => null);
    if (info && info.isDirectory()) { res.writeHead(301, { Location: path + '/' }).end(); return; }
    if (!info) { res.writeHead(404, { 'content-type': 'text/plain; charset=utf-8' }).end('404 — پیدا نشد'); return; }
    const body = await readFile(full);
    res.writeHead(200, {
      'content-type': MIME[extname(full).toLowerCase()] || 'application/octet-stream',
      'content-length': body.length,
      'cache-control': 'no-cache',
      'access-control-allow-origin': '*',
    });
    res.end(body);
  } catch (e) {
    res.writeHead(500, { 'content-type': 'text/plain; charset=utf-8' }).end('500 — ' + e.message);
  }
});

server.listen(port, '0.0.0.0', () => {
  console.log(`🎮 قارچ‌خور روی http://0.0.0.0:${port} آماده است (پوشهٔ ${root})`);
});
