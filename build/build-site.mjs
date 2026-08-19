// Builds the site GitHub Pages serves: the presentation page, and the documentation
// rendered from the same Markdown the repository shows.
//
// One source for the documentation, two places it can be read: docs/*.md on GitHub,
// and the same words as pages here. Nothing is written twice.
import { access, cp, mkdir, readdir, readFile, rm, writeFile } from "node:fs/promises";
import { basename, join } from "node:path";
import { marked } from "marked";
import { root, version } from "./lib.mjs";

const exists = async (path) => access(path).then(() => true, () => false);

const out = join(root, "dist", "site");
const v = await version();

await rm(out, { recursive: true, force: true });
await mkdir(join(out, "docs"), { recursive: true });

// The demo is the real front-end, not a recording of it, so the site carries the
// same bundle the packages do. The hotspot editor is admin-only and stays out.
const media = join(root, "dist", "media");

if (!(await exists(media))) {
  throw new Error("dist/media is missing: run npm run build:assets before the site");
}

for (const file of ["js/flipbook.js", "js/pdf.worker.mjs", "css/flipbook.css"]) {
  await mkdir(join(out, "media", file.split("/")[0]), { recursive: true });
  await cp(join(media, file), join(out, "media", file));
}

// The decoders too: the demo is a scan, and without them it is blank white pages.
await cp(join(media, "js/wasm"), join(out, "media", "js", "wasm"), { recursive: true });

await cp(join(media, "sounds"), join(out, "media", "sounds"), { recursive: true });
await cp(join(root, "site/demo"), join(out, "demo"), { recursive: true });

const demo = (await readFile(join(root, "site/demo.html"), "utf8"));
await writeFile(join(out, "demo.html"), demo, "utf8");

// The presentation page, with the version filled in so it cannot go stale.
const index = (await readFile(join(root, "site/index.html"), "utf8")).replace(
  ">—</span>",
  `>${v}</span>`,
);
await writeFile(join(out, "index.html"), index, "utf8");
await cp(join(root, "site/style.css"), join(out, "style.css"));
await cp(join(root, "site/img"), join(out, "img"), { recursive: true });

/** The shell every documentation page shares with the presentation page. */
function page(title, body, depth) {
  const up = "../".repeat(depth);

  return `<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>${title} — Hikari Flipbook</title>
<link rel="icon" href="${up}img/icon.svg" type="image/svg+xml">
<link rel="stylesheet" href="${up}style.css">
</head>
<body>

<header class="top">
  <div class="wrap">
    <a class="mark" href="${up}index.html">
      <img src="${up}img/icon.svg" alt="" width="28" height="28">
      <span>Hikari <strong>Flipbook</strong></span>
    </a>
    <nav>
      <a href="${up}demo.html">Demo</a>
      <a href="${up}docs/">Documentation</a>
      <a href="https://github.com/hikashop-nicolas/hikari-flipbook/releases/latest">Download</a>
      <a href="https://github.com/hikashop-nicolas/hikari-flipbook">GitHub</a>
    </nav>
  </div>
</header>

<main class="doc">
  <div class="wrap">
${body}
    <p class="crumb"><a href="${up}docs/">← All the documentation</a></p>
  </div>
</main>

<footer>
  <div class="wrap">
    Hikari Flipbook, by <a href="https://www.hikashop.com">Hikari Software</a>. GPL-3.0-or-later.
  </div>
</footer>

</body>
</html>
`;
}

const files = (await readdir(join(root, "docs"))).filter((f) => f.endsWith(".md"));

for (const file of files) {
  const markdown = await readFile(join(root, "docs", file), "utf8");
  // Links between the pages are written for GitHub, where they are .md files.
  const html = marked.parse(markdown.replace(/\((?!https?:)([\w-]+)\.md(#[\w-]+)?\)/g, "($1.html$2)"));
  const title = markdown.match(/^#\s+(.+)$/m)?.[1] ?? basename(file, ".md");
  const name = file === "README.md" ? "index.html" : file.replace(/\.md$/, ".html");

  await writeFile(join(out, "docs", name), page(title, html, 1), "utf8");
}

// GitHub Pages runs Jekyll by default, which ignores anything starting with an
// underscore and rewrites what it feels like. This turns it off.
await writeFile(join(out, ".nojekyll"), "", "utf8");

console.log(`site: dist/site (${files.length} documentation pages, a live demo, version ${v})`);
