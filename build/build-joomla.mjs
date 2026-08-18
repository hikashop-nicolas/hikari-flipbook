// The Joomla side ships as a package: a module, a content plugin, and one file
// extension carrying the viewer. The viewer is a couple of megabytes, so it is
// installed once and shared rather than duplicated into every extension.
import { cp, mkdir, readFile, rm, writeFile } from "node:fs/promises";
import { join } from "node:path";
import { guardAll, installCore, installMedia, root, version, zip } from "./lib.mjs";

const GUARD = "defined('_JEXEC') or die;";
const CORE = [
  [join(root, "src/joomla/JoomlaPlatform.php"), "JoomlaPlatform.php"],
  [join(root, "src/joomla/JoomlaBookStore.php"), "JoomlaBookStore.php"],
];

const v = await version();
// The extensions are staged outside the package root, so the package carries the
// installable zips and its manifest and nothing else.
const work = join(root, "dist", "joomla-work");
const stage = join(root, "dist", "joomla");
await rm(work, { recursive: true, force: true });
await rm(stage, { recursive: true, force: true });
await mkdir(join(stage, "packages"), { recursive: true });

/** Each extension gets its own copy of the core: they install independently. */
async function extension(name, from, files) {
  const dir = join(work, name);
  await mkdir(dir, { recursive: true });

  for (const entry of files) {
    await cp(join(from, entry), join(dir, entry), { recursive: true });
  }

  await installCore(dir, GUARD, CORE);
  await guardAll(dir, GUARD);
  await zip(dir, "", join(stage, "packages", `${name}.zip`));

  return dir;
}

await extension("mod_hikariflipbook", join(root, "src/joomla/mod_hikariflipbook"), [
  "mod_hikariflipbook.php",
  "mod_hikariflipbook.xml",
  "tmpl",
  "language",
]);

await extension("plg_content_hikariflipbook", join(root, "src/joomla/plg_content_hikariflipbook"), [
  "hikariflipbook.php",
  "hikariflipbook.xml",
  "language",
]);

// The component manages the books and reads none of the shared core: it is plain
// Joomla MVC over one table, so it ships without lib/.
const component = join(work, "com_hikariflipbook");
await mkdir(component, { recursive: true });
await cp(join(root, "src/joomla/com_hikariflipbook"), component, { recursive: true });
await guardAll(component, GUARD);
await zip(component, "", join(stage, "packages", "com_hikariflipbook.zip"));

// The file extension carries the media and nothing else, so it needs no core.
const files = join(work, "files_hikariflipbook");
await mkdir(files, { recursive: true });
await cp(
  join(root, "src/joomla/files_hikariflipbook/files_hikariflipbook.xml"),
  join(files, "files_hikariflipbook.xml"),
);
await installMedia(files);
await zip(files, "", join(stage, "packages", "files_hikariflipbook.zip"));

const manifest = await readFile(join(root, "src/joomla/pkg_hikariflipbook.xml"), "utf8");
await writeFile(join(stage, "pkg_hikariflipbook.xml"), manifest, "utf8");

const out = join(root, "dist", `pkg_hikariflipbook-${v}.zip`);
const bytes = await zip(stage, "", out);
console.log(`joomla: ${out} (${Math.round(bytes / 1024)} KB)`);
