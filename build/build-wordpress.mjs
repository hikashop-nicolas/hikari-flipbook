import { cp, mkdir, rm, writeFile } from "node:fs/promises";
import { join } from "node:path";
import { guardAll, installCore, installMedia, root, version, zip } from "./lib.mjs";

const GUARD = "if (!defined('ABSPATH')) {\n    exit;\n}";

const v = await version();
const work = join(root, "dist", "wordpress", "hikari-flipbook");
await rm(join(root, "dist", "wordpress"), { recursive: true, force: true });
await mkdir(join(work, "includes"), { recursive: true });
await mkdir(join(work, "languages"), { recursive: true });

await cp(join(root, "src/wordpress/hikari-flipbook.php"), join(work, "hikari-flipbook.php"));
await installCore(work, GUARD, [
  [join(root, "src/wordpress/includes/WordPressPlatform.php"), "WordPressPlatform.php"],
  [join(root, "src/wordpress/includes/WordPressBookStore.php"), "WordPressBookStore.php"],
]);

for (const name of ["Settings.php", "Books.php", "BookType.php"]) {
  await cp(join(root, "src/wordpress/includes", name), join(work, "includes", name));
}

await cp(join(root, "src/wordpress/blocks"), join(work, "blocks"), { recursive: true });
await installMedia(work);

await guardAll(work, GUARD);

// readme.txt is what wordpress.org reads: the Stable tag has to track the plugin.
await writeFile(
  join(work, "readme.txt"),
  `=== Hikari Flipbook ===
Contributors: hikarisoftware
Tags: flipbook, pdf, page flip, catalogue, viewer
Requires at least: 6.4
Tested up to: 6.8
Requires PHP: 8.1
Stable tag: ${v}
License: GPL-3.0-or-later
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Shows a PDF or a folder of images as a book with turning pages.

== Description ==

Turn a PDF or a folder of images into a book your visitors can leaf through, with
real page turns, zoom and deep links to a page. Nothing is uploaded anywhere: the
document is rendered in the visitor's browser.

== Installation ==

1. Install the plugin and activate it.
2. Put [hikari_flipbook path="uploads/catalogue.pdf"] in a post or page.

== Changelog ==

= ${v} =
* First release.
`,
  "utf8",
);

const out = join(root, "dist", `hikari-flipbook-${v}.zip`);
const bytes = await zip(work, "hikari-flipbook", out);
console.log(`wordpress: ${out} (${Math.round(bytes / 1024)} KB)`);
