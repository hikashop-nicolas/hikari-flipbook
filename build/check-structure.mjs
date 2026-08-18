// The rules that keep one codebase serving two hosts. Run in CI on every push,
// and locally with `npm run check`. Everything here is a hard failure: a warning
// nobody reads is how the two builds start drifting apart.
import { readFile, readdir, stat } from "node:fs/promises";
import { join, relative } from "node:path";
import { spawnSync } from "node:child_process";
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
const joomlaExtensions = [
  { name: "mod_hikariflipbook", manifest: "mod_hikariflipbook.xml", prefix: "MOD_HIKARIFLIPBOOK" },
  { name: "plg_content_hikariflipbook", manifest: "hikariflipbook.xml", prefix: "PLG_CONTENT_HIKARIFLIPBOOK" },
  {
    name: "com_hikariflipbook",
    manifest: "com_hikariflipbook.xml",
    prefix: "COM_HIKARIFLIPBOOK",
    // A component declares its files inside <administration>, and its language
    // sits under admin/ rather than at the root.
    filesIn: "administration",
    language: "admin/language",
  },
];

let joomla = null;
// The component keeps its copy of the core under admin/, where a component's
// PHP lives, so it is checked as well but from its own root.
let com = null;

for (const ext of joomlaExtensions) {
  const dir = join(root, "dist/joomla-work", ext.name);
  if (!(await exists(dir))) {
    fail(ext.name, "not built; run npm run build first");
    continue;
  }
  if (joomla === null) joomla = dir;
  if (ext.name === "com_hikariflipbook") com = join(dir, "admin");

  const files = (await walk(dir)).map((f) => relative(dir, f));
  const manifest = await read(join(dir, ext.manifest));

  const declared = manifest.match(/<version>([^<]+)<\/version>/)?.[1];
  if (declared !== v) fail(ext.name, `manifest version ${declared} does not match package.json ${v}`);

  // Every declared file has to exist, and every shipped top-level entry has to be declared.
  const filesBlock = ext.filesIn
    ? manifest.match(/<files folder="admin">([\s\S]*?)<\/files>/)?.[1] ?? ""
    : manifest.match(/<files>([\s\S]*?)<\/files>/)?.[1] ?? "";
  const named = [...filesBlock.matchAll(/<(?:filename|folder)[^>]*>([^<]+)</g)].map((m) => m[1]);
  const base = ext.filesIn ? join(dir, "admin") : dir;

  for (const entry of named) {
    if (!(await exists(join(base, entry)))) fail(ext.name, `manifest declares ${entry}, which is not in the package`);
  }
  for (const entry of await readdir(base)) {
    if (entry === ext.manifest || entry === "admin" || entry === "site") continue;
    if (!named.includes(entry)) fail(ext.name, `${entry} ships but the manifest does not declare it`);
  }

  // The viewer lives in the file extension, so nothing else may carry media.
  if (files.some((f) => f.startsWith("media/"))) {
    fail(ext.name, "ships its own media; the package installs one shared copy");
  }

  for (const file of files.filter((f) => f.endsWith(".php"))) {
    const body = await read(join(dir, file));
    const guards = (body.match(/defined\s*\(\s*['"]_JEXEC['"]\s*\)/g) || []).length;
    if (guards === 0) fail(ext.name, `${file} has no _JEXEC guard`);
    if (guards > 1) fail(ext.name, `${file} has ${guards} _JEXEC guards`);
    for (const rx of WORDPRESS_SYMBOLS) {
      if (rx.test(body)) fail(ext.name, `${file} names a WordPress symbol (${rx.source})`);
    }
  }

  for (const file of files.filter((f) => f.endsWith(".ini"))) {
    for (const line of (await read(join(dir, file))).split("\n")) {
      if (line.trim() === "" || line.startsWith(";")) continue;
      const key = line.split("=")[0];
      if (key !== key.toUpperCase()) fail(ext.name, `${file} key ${key} is not uppercase`);
      // HIKARI_FLIPBOOK_ is the shared core's own namespace: strings it asks for
      // by key, which both extensions have to be able to answer.
      const allowed = [ext.prefix, "HIKARI_FLIPBOOK_", "MOD_HIKARIFLIPBOOK", "J", "COM_"];
      if (!allowed.some((prefix) => key.startsWith(prefix))) {
        fail(ext.name, `${file} key ${key} is not prefixed`);
      }
    }
  }

  const locales = await readdir(join(dir, ext.language || "language"));
  if (locales.length !== 1 || locales[0] !== "en-GB") {
    fail(ext.name, `ships ${locales.join(", ")}; only en-GB belongs in the package`);
  }
}

// The package manifest has to name exactly the zips that were built.
const packageRoot = join(root, "dist/joomla");
if (await exists(packageRoot)) {
  const manifest = await read(join(packageRoot, "pkg_hikariflipbook.xml"));
  const built = (await readdir(join(packageRoot, "packages"))).sort();
  // \s after the tag name, so the <files> container does not match itself.
  const listed = [...manifest.matchAll(/<file\s[^>]*>([^<]+)<\/file>/g)].map((m) => m[1]).sort();

  for (const zipName of listed) {
    if (!built.includes(zipName)) fail("package", `the manifest names ${zipName}, which was not built`);
  }
  for (const zipName of built) {
    if (!listed.includes(zipName)) fail("package", `${zipName} was built but the manifest does not name it`);
  }
  if (manifest.match(/<version>([^<]+)<\/version>/)?.[1] !== v) {
    fail("package", `manifest version does not match package.json ${v}`);
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

  // The constant is what every asset URL is stamped with, so a stale one leaves
  // browsers on the old bundle after an update: the update appears not to work.
  const constant = main.match(/HIKARI_FLIPBOOK_VERSION['"],\s*['"]([^'"]+)/)?.[1];
  if (constant !== v) fail("wordpress", `HIKARI_FLIPBOOK_VERSION is ${constant}, not ${v}`);

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

  // Every global the plugin defines carries the prefix, since a plugin shares one
  // namespace with every other plugin on the site.
  for (const match of main.matchAll(/^function\s+(\w+)/gm)) {
    if (!match[1].startsWith("hikari_flipbook_")) {
      fail("wordpress", `${match[1]}() is a global without the plugin prefix`);
    }
  }
  for (const match of main.matchAll(/^(?:define|const)\s*\(?\s*['"]?(\w+)/gm)) {
    if (!match[1].startsWith("HIKARI_FLIPBOOK_")) {
      fail("wordpress", `${match[1]} is a global constant without the plugin prefix`);
    }
  }

  // The translator's catalogue has to be there, and to still match the code.
  const potFile = join(wp, "languages/hikari-flipbook.pot");
  if (!(await exists(potFile))) {
    fail("wordpress", "languages/hikari-flipbook.pot is missing; translators have nothing to work from");
  } else {
    const pot = await read(potFile);
    if (!pot.includes(`Hikari Flipbook ${v}`)) {
      fail("wordpress", `the catalogue was written for another version; run npm run pot`);
    }
    for (const needed of ["Open the book", "Next page"]) {
      if (!pot.includes(`msgid "${needed}"`)) {
        fail("wordpress", `the catalogue is missing "${needed}"; run npm run pot`);
      }
    }
  }

  // The block
  const blockFile = join(wp, "blocks/flipbook/block.json");
  if (!(await exists(blockFile))) {
    fail("wordpress", "blocks/flipbook/block.json is missing");
  } else {
    const block = JSON.parse(await read(blockFile));
    if (!String(block.name).startsWith("hikari-flipbook/")) {
      fail("wordpress", `the block is named ${block.name}, which is not under the plugin's namespace`);
    }
    if (block.textdomain !== "hikari-flipbook") {
      fail("wordpress", `the block declares text domain ${block.textdomain}`);
    }
    // Every attribute the editor edits has to be declared, or the block drops
    // it on the floor: an unregistered attribute simply does not survive.
    const editor = await read(join(wp, "blocks/flipbook/editor.js"));
    const edited = new Set([
      ...[...editor.matchAll(/field\(props,\s*'(\w+)'/g)].map((m) => m[1]),
      ...[...editor.matchAll(/props\.attributes\.(\w+)/g)].map((m) => m[1]),
      ...[...editor.matchAll(/setAttributes\(\{\s*(\w+):/g)].map((m) => m[1]),
    ]);
    for (const name of edited) {
      if (!Object.prototype.hasOwnProperty.call(block.attributes ?? {}, name)) {
        fail("wordpress", `the editor edits "${name}", which block.json does not declare`);
      }
    }

    for (const key of ["editorScript", "script", "style", "viewScript"]) {
      const ref = block[key];
      if (typeof ref !== "string" || !ref.startsWith("file:./")) continue;
      if (!(await exists(join(wp, "blocks/flipbook", ref.slice("file:./".length))))) {
        fail("wordpress", `block.json points ${key} at ${ref}, which is not in the package`);
      }
    }
  }
}

// --- both hosts ship the same media ------------------------------------------
// One bundle, two packages: an asset that reaches one host and not the other is
// a feature that silently exists on Joomla and not on WordPress.
const built = join(root, "dist/media");
if (await exists(built)) {
  const wanted = (await walk(built)).map((f) => relative(built, f)).sort();

  for (const [label, dir] of [
    ["joomla", join(root, "dist/joomla-work/files_hikariflipbook/media")],
    ["wordpress", join(wp, "media")],
  ]) {
    if (!(await exists(dir))) {
      fail(label, "ships no media at all");
      continue;
    }
    const shipped = new Set((await walk(dir)).map((f) => relative(dir, f)));
    for (const file of wanted) {
      if (!shipped.has(file)) fail(label, `media/${file} was built but does not ship`);
    }
  }
}

// --- everything shipped in lib/ is actually loaded ---------------------------
for (const [label, dir] of [["joomla", joomla], ["wordpress", wp], ["com_hikariflipbook", com]]) {
  if (dir === null || !(await exists(join(dir, "lib")))) continue;
  const bootstrap = await read(join(dir, "lib/bootstrap.php"));
  for (const file of (await walk(join(dir, "lib"))).map((f) => relative(join(dir, "lib"), f))) {
    if (!file.endsWith(".php") || file === "bootstrap.php") continue;
    if (!bootstrap.includes(`/${file}'`)) {
      fail(label, `lib/${file} ships but bootstrap.php never requires it`);
    }
  }

  // Every require has to be guarded by what the file declares. Two extensions
  // ship two copies of the core and can both be loaded by one request, from
  // different paths, which require_once cannot see: the guard is the only thing
  // between that and a fatal redeclare.
  for (const line of bootstrap.split("\n")) {
    if (!line.includes("require_once __DIR__")) continue;
    if (!/^\s+require_once/.test(line)) {
      fail(label, `bootstrap.php requires ${line.trim()} without a class_exists guard`);
    }
  }

  // Without an autoloader, order is load order: the interface has to come first.
  const lines = bootstrap.split("\n").filter((l) => l.includes("require_once"));
  const iface = lines.findIndex((l) => l.includes("platform/Platform.php"));
  const adapter = lines.findIndex((l) => /platform\/(?!Platform\.php)/.test(l));
  if (iface === -1) fail(label, "bootstrap.php never requires the Platform interface");
  if (adapter !== -1 && adapter < iface) {
    fail(label, "bootstrap.php requires the host adapter before the interface it implements");
  }
}

// --- two copies of the core can be loaded by one request ---------------------
// The static rule above says the guards are there; this proves they work, by
// loading the module's copy and the plugin's copy the way a page with both on it
// does. It is the bug this whole arrangement exists to prevent.
{
  const copies = [
    join(root, "dist/joomla-work/mod_hikariflipbook/lib/bootstrap.php"),
    join(root, "dist/joomla-work/plg_content_hikariflipbook/lib/bootstrap.php"),
    join(root, "dist/joomla-work/com_hikariflipbook/admin/lib/bootstrap.php"),
  ];

  if ((await Promise.all(copies.map(exists))).every(Boolean)) {
    const script = "define('_JEXEC', 1); " + copies.map((f) => `require '${f}';`).join(" ");
    const php = spawnSync("php", ["-d", "error_reporting=E_ALL", "-r", script], { encoding: "utf8" });

    if (php.error) {
      console.error("  (php is not available; the double-load check was skipped)");
    } else if (php.status !== 0) {
      fail("joomla", `two extensions cannot load the core in one request: ${(php.stderr || php.stdout).trim().split("\n")[0]}`);
    }
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
