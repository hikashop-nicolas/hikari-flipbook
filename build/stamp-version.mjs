// Writes the version from package.json into everything that carries it.
//
// Joomla reads a version out of each manifest, WordPress out of the plugin
// header, and the update file has to name the release the tag will build. They
// have to agree or a site is offered a download that does not exist, so this
// runs from npm's own "version" step rather than being remembered.
import { readFile, writeFile } from "node:fs/promises";
import { join } from "node:path";
import { root, version } from "./lib.mjs";

const v = await version();

/** Each manifest's own <version>, which Joomla shows and compares against. */
const MANIFESTS = [
  "src/joomla/pkg_hikariflipbook.xml",
  "src/joomla/mod_hikariflipbook/mod_hikariflipbook.xml",
  "src/joomla/plg_content_hikariflipbook/hikariflipbook.xml",
  "src/joomla/com_hikariflipbook/com_hikariflipbook.xml",
  "src/joomla/files_hikariflipbook/files_hikariflipbook.xml",
];

for (const path of MANIFESTS) {
  const file = join(root, path);
  const before = await readFile(file, "utf8");
  const after = before.replace(/<version>[^<]+<\/version>/, `<version>${v}</version>`);

  if (before !== after) await writeFile(file, after, "utf8");
}

const plugin = join(root, "src/wordpress/hikari-flipbook.php");
const before = await readFile(plugin, "utf8");
const after = before
  .replace(/^( \* Version: +)\S+$/m, `$1${v}`)
  .replace(/define\('HIKARI_FLIPBOOK_VERSION', '[^']+'\)/, `define('HIKARI_FLIPBOOK_VERSION', '${v}')`);

if (before !== after) await writeFile(plugin, after, "utf8");

console.log(`version: ${v} written into ${MANIFESTS.length} manifests and the plugin header`);
