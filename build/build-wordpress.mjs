import { cp, mkdir, rm, writeFile } from "node:fs/promises";
import { join } from "node:path";
import { guardAll, installCore, installMedia, root, version, zip } from "./lib.mjs";

const GUARD = "if (!defined('ABSPATH')) {\n    exit;\n}";

const v = await version();
const work = join(root, "dist", "wordpress", "hikari-flipbook");
await rm(join(root, "dist", "wordpress"), { recursive: true, force: true });
await mkdir(join(work, "includes"), { recursive: true });
await mkdir(join(work, "languages"), { recursive: true });
await cp(
  join(root, "src/wordpress/languages/hikari-flipbook.pot"),
  join(work, "languages/hikari-flipbook.pot"),
);

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
Tested up to: 7.0
Requires PHP: 8.1
Stable tag: ${v}
License: GPL-3.0-or-later
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Shows a PDF, an EPUB, a Kindle book, a comic archive or a folder of pages as a book with turning pages.

== Description ==

Turn a PDF, an EPUB, a Kindle book, a FictionBook, a comic archive, a folder of images or a folder of HTML pages
into a book your visitors can leaf through, with real page turns, zoom and deep links
to a page. Nothing is uploaded anywhere: the document is rendered in the visitor's
browser.

Features:

* Real page turns, on a phone as well as a desktop, with a page turn sound you can
  choose or replace.
* Search inside the document, its own table of contents, and thumbnails of every page.
* Hotspots: draw a region on a page and bind it to a link, another page, or a
  WooCommerce product, which turns a catalogue into a shoppable one.
* The document's text goes into the page as well, so search engines can read a
  catalogue that is otherwise a picture of one.
* Page turns, searches and hotspot clicks are reported to the page as an event, and
  to Google Tag Manager or Google Analytics if you want them there.
* A book can be sold: name the product it belongs to and only a visitor who has
  bought it is shown the book, on WooCommerce or on HikaShop. With no file of its
  own it shows the one the product is sold with, read out by the site rather than
  left in a public folder.
* Nothing is sent to us, and the plugin loads nothing from anyone else's server.

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
