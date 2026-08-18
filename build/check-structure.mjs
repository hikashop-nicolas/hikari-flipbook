// The rules that keep one codebase serving two hosts. Run in CI on every push,
// and locally with `npm run check`. Everything here is a hard failure: a warning
// nobody reads is how the two builds start drifting apart.
import { readFile, readdir, stat } from "node:fs/promises";
import { join, relative } from "node:path";
import { root, version } from "./lib.mjs";

const problems = [];
const fail = (where, what) => problems.push(`${where}: ${what}`);

const JOOMLA_SYMBOLS = [/\bJoomla\\\\/, /\bJFactory\b/, /\bJPATH_/, /Factory::getApplication/];
const WORDPRESS_SYMBOLS = [/\badd_action\s*\(/, /\badd_filter\s*\(/, /\bwp_[a-z_]+\s*\(/, /\bABSPATH\b/, /\bget_option\s*\(/];

async function walk(dir) {
  const out = [];
  for (const entry of await readdir(dir, { withFileTypes: true })) {
    const path = join(dir, entry.name);
    if (entry.isDirectory()) out.push(...(await walk(path)));
    else out.push(path);
  }
  return out;
}

const read = (p) => readFile(p, "utf8");
const exists = async (p) => !!(await stat(p).catch(() => null));

// --- the shared core belongs to neither host -------------------------------
for (const dir of ["src/core", "src/platform"]) {
  for (const file of await walk(join(root, dir))) {
    const body = await read(file);
    const name = relative(root, file);
    for (const rx of JOOMLA_SYMBOLS) {
      if (rx.test(body)) fail(name, `names a Joomla symbol (${rx.source}); the core must go through Platform`);
    }
    for (const rx of WORDPRESS_SYMBOLS) {
      if (rx.test(body)) fail(name, `names a WordPress symbol (${rx.source}); the core must go through Platform`);
    }
    if (/\$_(GET|POST|REQUEST|SERVER)\b/.test(body)) {
      fail(name, "reads a superglobal directly; ask the host instead");
    }
  }
}

const v = await version();

// --- the Joomla package -----------------------------------------------------
const joomla = join(root, "dist/joomla/mod_hikariflipbook");
if (!(await exists(joomla))) {
  fail("joomla", "not built; run npm run build first");
} else {
  const files = (await walk(joomla)).map((f) => relative(joomla, f));
  const manifest = await read(join(joomla, "mod_hikariflipbook.xml"));

  if (!/<version>([^<]+)<\/version>/.test(manifest)) fail("joomla", "manifest has no version");
  const declared = manifest.match(/<version>([^<]+)<\/version>/)?.[1];
  if (declared !== v) fail("joomla", `manifest version ${declared} does not match package.json ${v}`);

  // Every declared file has to exist, and every shipped top-level entry has to be
  // declared. The <media> block is separate: its folders live under media/.
  const filesBlock = manifest.match(/<files>([\s\S]*?)<\/files>/)?.[1] ?? "";
  const mediaBlock = manifest.match(/<media[^>]*>([\s\S]*?)<\/media>/)?.[1] ?? "";
  const named = [...filesBlock.matchAll(/<(?:filename|folder)[^>]*>([^<]+)</g)].map((m) => m[1]);
  const mediaNamed = [...mediaBlock.matchAll(/<(?:filename|folder)[^>]*>([^<]+)</g)].map((m) => m[1]);

  for (const entry of named) {
    if (!(await exists(join(joomla, entry)))) fail("joomla", `manifest declares ${entry}, which is not in the package`);
  }
  for (const entry of mediaNamed) {
    if (!(await exists(join(joomla, "media", entry)))) {
      fail("joomla", `manifest declares media/${entry}, which is not in the package`);
    }
  }
  const top = new Set(files.map((f) => f.split("/")[0]));
  for (const entry of top) {
    if (entry === "media" || entry === "mod_hikariflipbook.xml") continue;
    if (!named.includes(entry)) fail("joomla", `${entry} ships but the manifest does not declare it`);
  }

  for (const file of files.filter((f) => f.endsWith(".php"))) {
    const body = await read(join(joomla, file));
    const guards = (body.match(/defined\s*\(\s*['"]_JEXEC['"]\s*\)/g) || []).length;
    if (guards === 0) fail("joomla", `${file} has no _JEXEC guard`);
    if (guards > 1) fail("joomla", `${file} has ${guards} _JEXEC guards`);
    for (const rx of WORDPRESS_SYMBOLS) {
      if (rx.test(body)) fail("joomla", `${file} names a WordPress symbol (${rx.source})`);
    }
  }

  for (const file of files.filter((f) => f.endsWith(".ini"))) {
    for (const line of (await read(join(joomla, file))).split("\n")) {
      if (line.trim() === "" || line.startsWith(";")) continue;
      const key = line.split("=")[0];
      if (key !== key.toUpperCase()) fail("joomla", `${file} key ${key} is not uppercase`);
      if (!key.startsWith("MOD_HIKARIFLIPBOOK") && !key.startsWith("J") && !key.startsWith("COM_")) {
        fail("joomla", `${file} key ${key} is not prefixed`);
      }
    }
  }

  const locales = await readdir(join(joomla, "language"));
  if (locales.length !== 1 || locales[0] !== "en-GB") {
    fail("joomla", `ships ${locales.join(", ")}; only en-GB belongs in the package`);
  }
}

// --- the WordPress package --------------------------------------------------
const wp = join(root, "dist/wordpress/hikari-flipbook");
if (!(await exists(wp))) {
  fail("wordpress", "not built; run npm run build first");
} else {
  const files = (await walk(wp)).map((f) => relative(wp, f));
  const main = await read(join(wp, "hikari-flipbook.php"));

  if (!/^\s*\*\s*Plugin Name:/m.test(main)) fail("wordpress", "the plugin header is missing");
  const headerVersion = main.match(/^\s*\*\s*Version:\s*(.+)$/m)?.[1]?.trim();
  if (headerVersion !== v) fail("wordpress", `header version ${headerVersion} does not match package.json ${v}`);
  if (!/Text Domain:\s*hikari-flipbook/.test(main)) fail("wordpress", "the header names no text domain");

  const readme = await read(join(wp, "readme.txt"));
  const stable = readme.match(/^Stable tag:\s*(.+)$/m)?.[1]?.trim();
  if (stable !== v) fail("wordpress", `readme.txt stable tag ${stable} does not match package.json ${v}`);

  for (const file of files.filter((f) => f.endsWith(".php"))) {
    const body = await read(join(wp, file));
    if (!/defined\s*\(\s*['"]ABSPATH['"]\s*\)/.test(body)) fail("wordpress", `${file} has no ABSPATH guard`);
    for (const rx of JOOMLA_SYMBOLS) {
      if (rx.test(body)) fail("wordpress", `${file} names a Joomla symbol (${rx.source})`);
    }
    for (const call of body.matchAll(/\b(?:__|_e|esc_html__|esc_attr__)\s*\(\s*[^,)]+,\s*([^)]+)\)/g)) {
      if (!call[1].includes("'hikari-flipbook'")) {
        fail("wordpress", `${file} translates against ${call[1].trim()} rather than the plugin text domain`);
      }
    }
  }

  for (const name of ["hikari_flipbook_shortcode"]) {
    if (!main.includes(name)) fail("wordpress", `${name} is missing; global functions carry the plugin prefix`);
  }
}

// --- neither package ships the repository ------------------------------------
for (const [label, dir] of [["joomla", joomla], ["wordpress", wp]]) {
  if (!(await exists(dir))) continue;
  const files = (await walk(dir)).map((f) => relative(dir, f));
  for (const file of files) {
    if (/(^|\/)(assets|_plans|build|tests|node_modules)\//.test(file)) {
      fail(label, `${file} is repository material and must not ship`);
    }
    if (/\.(map|bak|zip)$/.test(file) || /(^|\/)\.(git|DS_Store)/.test(file)) {
      fail(label, `${file} does not belong in a package`);
    }
  }
}

// --- the viewer that shipped is the viewer we pinned --------------------------
const pkg = JSON.parse(await read(join(root, "package.json")));
const pin = pkg.dependencies.flipview.split("#")[1];
const installed = JSON.parse(await read(join(root, "node_modules/flipview/package.json"))).version;
if (pin && pin.replace(/^v/, "") !== installed) {
  fail("assets", `flipview ${installed} is installed but package.json pins ${pin}`);
}

if (problems.length === 0) {
  console.log("structure: all rules pass");
  process.exit(0);
}

console.error(`structure: ${problems.length} problem${problems.length === 1 ? "" : "s"}`);
for (const p of problems) console.error("  " + p);
process.exit(1);
