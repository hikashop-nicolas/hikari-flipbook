// Writes the translator's catalogue for the WordPress plugin.
//
// wp i18n make-pot would do this, but it needs WP-CLI installed; the plugin is a
// handful of files, so the strings are read out of them directly. Every __(),
// _e() and esc_html__() call against the plugin's own text domain becomes a
// msgid, along with the strings the shared core asks a host to translate.
import { execFileSync } from "node:child_process";
import { readFile, readdir, writeFile } from "node:fs/promises";
import { join } from "node:path";
import { root, version } from "./lib.mjs";

const DOMAIN = "hikari-flipbook";
const strings = new Map(); // msgid -> [references]

function note(msgid, reference) {
  if (!msgid) return;
  if (!strings.has(msgid)) strings.set(msgid, []);
  strings.get(msgid).push(reference);
}

async function scan(dir, prefix) {
  for (const entry of await readdir(dir, { withFileTypes: true })) {
    const path = join(dir, entry.name);
    if (entry.isDirectory()) {
      await scan(path, `${prefix}${entry.name}/`);
      continue;
    }
    if (!/\.(php|js)$/.test(entry.name)) continue;

    const body = await readFile(path, "utf8");
    const lines = body.split("\n");

    lines.forEach((line, index) => {
      const calls = line.matchAll(
        /\b(?:__|_e|esc_html__|esc_attr__|esc_html_e)\(\s*(['"])((?:\\.|(?!\1).)*)\1\s*,\s*(['"])hikari-flipbook\3/g,
      );
      for (const call of calls) {
        note(call[2].replace(/\\'/g, "'").replace(/\\"/g, '"'), `${prefix}${entry.name}:${index + 1}`);
      }
    });
  }
}

await scan(join(root, "src/wordpress"), "");

// The shared core asks for these by key; the English is what gettext translates.
const catalogue = JSON.parse(
  execFileSync("php", [
    "-r",
    "require 'src/platform/Platform.php'; require 'src/core/Strings.php';" +
      " echo json_encode(Hikari\\Flipbook\\Core\\Strings::catalogue());",
  ], { cwd: root, encoding: "utf8" }),
);
for (const english of Object.values(catalogue)) note(english, "src/core/Strings.php");

const v = await version();
const header = `# Copyright (C) 2026 Hikari Software
# This file is distributed under the GPL-3.0-or-later licence.
msgid ""
msgstr ""
"Project-Id-Version: Hikari Flipbook ${v}\\n"
"Report-Msgid-Bugs-To: https://github.com/hikashop-nicolas/hikari-flipbook/issues\\n"
"MIME-Version: 1.0\\n"
"Content-Type: text/plain; charset=UTF-8\\n"
"Content-Transfer-Encoding: 8bit\\n"
"X-Domain: ${DOMAIN}\\n"
`;

const body = [...strings.entries()]
  .sort(([a], [b]) => a.localeCompare(b))
  .map(([msgid, refs]) => `\n#: ${refs.join(" ")}\nmsgid "${msgid.replace(/"/g, '\\"')}"\nmsgstr ""\n`)
  .join("");

const out = join(root, "src/wordpress/languages", `${DOMAIN}.pot`);
await writeFile(out, header + body, "utf8");
console.log(`pot: ${strings.size} strings into ${out}`);
