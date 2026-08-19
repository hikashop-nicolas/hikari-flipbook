// Builds the site GitHub Pages serves: the presentation page, and the documentation
// rendered from the same Markdown the repository shows.
//
// One source for the documentation, two places it can be read: docs/*.md on GitHub,
// and the same words as pages here. Nothing is written twice.
import { createHash } from "node:crypto";
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

// Assets carry the version, so a browser that saw the last build does not keep
// showing it. Everything here is served from one folder with no hashing.
/**
 * Stamps every asset a page names with a hash of that file's contents.
 *
 * The version was the obvious thing to stamp with and it was wrong: two builds of
 * the same version produce the same URL, so a browser that saw the first one keeps
 * showing it however many times the file changes underneath. A hash changes when
 * the bytes change, which is the only thing that matters here.
 */
async function stamp(html) {
  const seen = new Map();

  const hashOf = async (path) => {
    if (seen.has(path)) return seen.get(path);

    let hash = "";

    try {
      hash = createHash("sha256")
        .update(await readFile(join(out, path)))
        .digest("hex")
        .slice(0, 8);
    } catch {
      // A path the site does not serve: left alone, and the rules will say so.
    }

    seen.set(path, hash);

    return hash;
  };

  const paths = [
    ...html.matchAll(/(demo\/[\w./-]+\.(?:pdf|epub|jpg|png|html))/g),
    ...html.matchAll(/(media\/(?:js|css)\/[\w.-]+)"/g),
  ].map((match) => match[1]);

  let out2 = html;

  for (const path of new Set(paths)) {
    const hash = await hashOf(path);
    if (!hash) continue;

    out2 = out2.split(path).join(`${path}?v=${hash}`);
  }

  return out2;
}

await writeFile(
  join(out, "demo.html"),
  await stamp(await readFile(join(root, "site/demo.html"), "utf8")),
  "utf8",
);

// The presentation page, with the version filled in so it cannot go stale.
const index = (await readFile(join(root, "site/index.html"), "utf8")).replace(
  ">—</span>",
  `>${v}</span>`,
);
await writeFile(join(out, "index.html"), index, "utf8");
await cp(join(root, "site/style.css"), join(out, "style.css"));
await cp(join(root, "site/img"), join(out, "img"), { recursive: true });

/** The shell every documentation page shares with the presentation page. */
function page(title, body, depth, aside) {
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
  <div class="wrap doc-grid">
    <aside class="side">
      <nav aria-label="Documentation">
        <h2>Documentation</h2>
        <ul>
${aside}
        </ul>
      </nav>
    </aside>
    <article>
${body}
    </article>
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
const written = new Map();

for (const file of files) {
  const markdown = await readFile(join(root, "docs", file), "utf8");
  // Links between the pages are written for GitHub, where they are .md files.
  const html = marked.parse(markdown.replace(/\((?!https?:)([\w-]+)\.md(#[\w-]+)?\)/g, "($1.html$2)"));

  written.set(file, {
    html,
    title: markdown.match(/^#\s+(.+)$/m)?.[1] ?? basename(file, ".md"),
    name: file === "README.md" ? "index.html" : file.replace(/\.md$/, ".html"),
    markdown,
  });
}

// The order of the side navigation, and the words in it, are the list the front
// page of the documentation already carries. A second list would be a second
// thing to keep right. Anything not on that list still gets a place, at the end.
const listed = [...(written.get("README.md")?.markdown ?? "").matchAll(
  /\[([^\]]+)\]\((?!https?:)([\w-]+)\.md\)/g,
)].map((match) => ({ label: match[1], file: `${match[2]}.md` }));

const order = [
  { label: "Overview", file: "README.md" },
  ...listed.filter((entry) => written.has(entry.file)),
  ...files
    .filter((file) => file !== "README.md" && !listed.some((entry) => entry.file === file))
    .sort()
    .map((file) => ({ label: written.get(file).title, file })),
];

/** The same list on every page, with the page being read marked as such. */
function aside(here) {
  return order
    .map(({ label, file }) => {
      const at = written.get(file).name;
      const mark = file === here ? ' aria-current="page"' : "";

      return `          <li><a href="${at}"${mark}>${label}</a></li>`;
    })
    .join("\n");
}

for (const [file, doc] of written) {
  await writeFile(join(out, "docs", doc.name), page(doc.title, doc.html, 1, aside(file)), "utf8");
}

// GitHub Pages runs Jekyll by default, which ignores anything starting with an
// underscore and rewrites what it feels like. This turns it off.
await writeFile(join(out, ".nojekyll"), "", "utf8");

console.log(`site: dist/site (${files.length} documentation pages, a live demo, version ${v})`);
