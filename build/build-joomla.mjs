import { cp, mkdir, rm } from "node:fs/promises";
import { join } from "node:path";
import { guardAll, installCore, installMedia, root, version, zip } from "./lib.mjs";

const GUARD = "defined('_JEXEC') or die;";

const v = await version();
const work = join(root, "dist", "joomla", "mod_hikariflipbook");
await rm(join(root, "dist", "joomla"), { recursive: true, force: true });
await mkdir(work, { recursive: true });

const from = join(root, "src/joomla/mod_hikariflipbook");
for (const entry of ["mod_hikariflipbook.php", "mod_hikariflipbook.xml", "tmpl", "language"]) {
  await cp(join(from, entry), join(work, entry), { recursive: true });
}

await installCore(work, GUARD);
await cp(join(root, "src/joomla/JoomlaPlatform.php"), join(work, "lib/platform/JoomlaPlatform.php"));
await installMedia(work);

// Everything else that was copied in verbatim needs the guard too: the module
// entry, the layouts and the adapter carry none in source.
await guardAll(work, GUARD);

const out = join(root, "dist", `mod_hikariflipbook-${v}.zip`);
const bytes = await zip(work, "", out);
console.log(`joomla: ${out} (${Math.round(bytes / 1024)} KB)`);
