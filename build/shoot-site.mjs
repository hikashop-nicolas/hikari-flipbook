// Takes the two pictures the home page shows, from the demo the site ships, so the
// pictures are the running product rather than a mock-up. Headless Chrome over the
// devtools protocol; no dependency beyond Chrome itself.
//
//   node build/serve-site.mjs &   # or npm run site:serve
//   node build/shoot-site.mjs
import { spawn } from "node:child_process";
import { mkdtemp, rm, writeFile } from "node:fs/promises";
import { execFile } from "node:child_process";
import { promisify } from "node:util";
import { tmpdir } from "node:os";
import { join } from "node:path";
import { root } from "./lib.mjs";

const CHROME = "/Applications/Google Chrome.app/Contents/MacOS/Google Chrome";
const PORT = Number(process.env.PORT || 4173);
const DEBUG = 9333;
const WIDTH = 1500;
const HEIGHT = 1300;
const SCALE = 2;

const wait = (ms) => new Promise((done) => setTimeout(done, ms));
const run = promisify(execFile);

const shots = [
  // The home page shows the viewer itself, then what a hotspot looks like over a page.
  { file: "book.jpg", url: `http://localhost:${PORT}/demo.html?book=pdf#page=5`, panel: true, spots: false },
  { file: "hotspots.jpg", url: `http://localhost:${PORT}/demo.html?book=pdf#page=5`, panel: false, spots: true },
];

const profile = await mkdtemp(join(tmpdir(), "flipshot-"));
const chrome = spawn(CHROME, [
  "--headless=new",
  `--remote-debugging-port=${DEBUG}`,
  `--user-data-dir=${profile}`,
  `--window-size=${WIDTH},${HEIGHT}`,
  "--hide-scrollbars",
  "--no-first-run",
  "--disable-gpu",
  "about:blank",
], { stdio: "ignore" });

let socket = null;
try {
  // The browser needs a moment before it answers, and the page target is the tab.
  let list = null;
  for (let tries = 0; tries < 40 && !list; tries++) {
    await wait(250);
    try {
      list = await (await fetch(`http://localhost:${DEBUG}/json/list`)).json();
    } catch { /* not up yet */ }
  }
  const page = list.find((t) => t.type === "page");
  socket = new WebSocket(page.webSocketDebuggerUrl);
  await new Promise((ok, no) => { socket.onopen = ok; socket.onerror = no; });

  let id = 0;
  const pending = new Map();
  socket.onmessage = (event) => {
    const message = JSON.parse(event.data);
    const waiting = pending.get(message.id);
    if (!waiting) return;
    pending.delete(message.id);
    if (message.error) waiting.no(new Error(message.error.message));
    else waiting.ok(message.result);
  };
  const send = (method, params = {}) => new Promise((ok, no) => {
    const mine = ++id;
    pending.set(mine, { ok, no });
    socket.send(JSON.stringify({ id: mine, method, params }));
  });
  const evaluate = async (expression) => {
    const answer = await send("Runtime.evaluate", { expression, returnByValue: true, awaitPromise: true });
    if (answer.exceptionDetails) throw new Error(answer.exceptionDetails.text);
    return answer.result.value;
  };

  await send("Page.enable");
  // Set on the page rather than by a flag: a device scale factor passed to Chrome
  // shrinks the viewport itself, and the viewer lays out for the width it is given.
  await send("Emulation.setDeviceMetricsOverride", {
    width: WIDTH,
    height: HEIGHT,
    deviceScaleFactor: SCALE,
    mobile: false,
  });
  // The site follows the reader's theme, and headless Chrome asks for the dark one.
  await send("Emulation.setEmulatedMedia", {
    features: [{ name: "prefers-color-scheme", value: "light" }],
  });

  for (const shot of shots) {
    // Cleared between shots: the two ask for the same address, and navigating to
    // an address the tab is already at only moves the fragment.
    await send("Page.navigate", { url: "about:blank" });
    await wait(200);
    await send("Page.navigate", { url: shot.url });
    // The book is painted by a worker: wait for the pages rather than for a delay.
    for (let tries = 0; tries < 80; tries++) {
      await wait(250);
      if (await evaluate("document.querySelectorAll('.fv-rendered').length >= 2")) break;
    }
    if (!shot.spots) {
      await evaluate("document.head.insertAdjacentHTML('beforeend', '<style>.fv-hotspot{display:none}</style>')");
    }
    if (shot.panel) {
      await evaluate("document.querySelector('.fv-btn-panel').click()");
      // Every thumbnail is painted by the same worker as the pages are.
      for (let tries = 0; tries < 80; tries++) {
        await wait(250);
        const painted = await evaluate(
          "[...document.querySelectorAll('.fv-thumb-img')].filter((i) => i.naturalWidth > 0).length"
        );
        if (painted >= 5) break;
      }
      await wait(1500);
    }
    await wait(600);
    const box = await evaluate(`(() => {
      const book = document.querySelector('.fv-root');
      const it = book.getBoundingClientRect();
      return { x: it.x + scrollX, y: it.y + scrollY, width: it.width, height: it.height };
    })()`);
    if (process.env.SHOT_DEBUG) {
      console.log(shot.file, await evaluate(`JSON.stringify({
        w: innerWidth, h: innerHeight, dpr: devicePixelRatio,
        root: document.querySelector('.fv-root')?.getBoundingClientRect().toJSON(),
        book: document.querySelector('.fv-book')?.getBoundingClientRect().toJSON(),
        stage: document.querySelector('.fv-stage')?.getBoundingClientRect().toJSON(),
      })`));
    }
    const pad = 14;
    // Chrome's own clip is unreliable once a scale factor is in play, so the whole
    // viewport is taken and the region cut out of it afterwards.
    const shotted = await send("Page.captureScreenshot", { format: "png" });
    const whole = join(profile, "whole.png");
    await writeFile(whole, Buffer.from(shotted.data, "base64"));
    const to = join(root, "site", "img", shot.file);
    await run("sips", [
      "-c",
      String(Math.round((box.height + pad * 2) * SCALE)),
      String(Math.round((box.width + pad * 2) * SCALE)),
      "--cropOffset",
      String(Math.round((box.y - pad) * SCALE)),
      String(Math.round((box.x - pad) * SCALE)),
      "-s", "format", "jpeg",
      "-s", "formatOptions", "88",
      whole,
      "--out", to,
    ]);
    console.log(`wrote site/img/${shot.file} (${Math.round(box.width)}x${Math.round(box.height)} css px)`);
  }
} finally {
  socket?.close();
  chrome.kill();
  // The profile is still being written to for a moment after the kill.
  await new Promise((done) => chrome.once("exit", done));
  await wait(300);
  await rm(profile, { recursive: true, force: true, maxRetries: 5 });
}
