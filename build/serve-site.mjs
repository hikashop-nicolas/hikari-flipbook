// Serves dist/site so the site can be looked at before anything is pushed.
// Node's own http and fs, nothing installed.
import { createServer } from "node:http";
import { createReadStream } from "node:fs";
import { stat } from "node:fs/promises";
import { extname, join, normalize } from "node:path";
import { root } from "./lib.mjs";

const dir = join(root, "dist", "site");
const port = Number(process.env.PORT || 4173);

const TYPES = {
  ".html": "text/html; charset=utf-8",
  ".css": "text/css; charset=utf-8",
  ".js": "text/javascript; charset=utf-8",
  ".mjs": "text/javascript; charset=utf-8",
  ".json": "application/json",
  ".svg": "image/svg+xml",
  ".png": "image/png",
  ".jpg": "image/jpeg",
  ".pdf": "application/pdf",
  ".mp3": "audio/mpeg",
};

createServer(async (req, res) => {
  // Everything is inside dist/site or it is not served at all.
  const asked = decodeURIComponent((req.url || "/").split("?")[0]);
  let path = join(dir, normalize(asked).replace(/^(\.\.[/\\])+/, ""));

  try {
    if ((await stat(path)).isDirectory()) path = join(path, "index.html");
  } catch {
    res.writeHead(404, { "content-type": "text/plain" });
    res.end("Not here");
    return;
  }

  res.writeHead(200, {
    "content-type": TYPES[extname(path)] || "application/octet-stream",
    // Never cached: this server exists to look at a change that was just made,
    // and a stale stylesheet here reads exactly like the bug you just fixed.
    "cache-control": "no-store",
  });
  createReadStream(path).pipe(res);
}).listen(port, () => {
  console.log(`\nThe site is at http://localhost:${port}/  (demo: /demo.html)`);
  console.log("Stop it with ctrl-c.\n");
});
