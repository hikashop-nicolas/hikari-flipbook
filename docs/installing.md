# Installing it

## Joomla

Install `pkg_hikariflipbook-<version>.zip` through **System, Install, Extensions**.
The package installs four things:

| Piece | What it is for |
|---|---|
| Component, Hikari Flipbook | The book manager: define a book once, place it anywhere |
| Module, Hikari Flipbook | A book in a module position |
| Content plugin | `{flipbook ...}` inside an article |
| File extension | The viewer itself, installed once and shared by the rest |

Joomla 4, 5 and 6, PHP 8.1 or newer.

### Updates

The package registers its own update site, so **System, Update, Extensions** offers
new versions like any other extension. The update file and the packages both come
from the project's GitHub repository; nothing else is contacted, and there is no key
to enter.

## WordPress

WordPress 6.4 or newer, PHP 8.1 or newer.

- **From the plugin directory**: search for Hikari Flipbook under **Plugins, Add New**,
  install and activate. Updates then arrive the way every other listed plugin's do.
- **By hand**: download `hikari-flipbook-<version>.zip` from the
  [releases page](https://github.com/hikashop-nicolas/hikari-flipbook/releases), then
  **Plugins, Add New, Upload Plugin**. A plugin installed this way does not update
  itself: install the newer zip over it, or switch to the directory version.

## Where a document may live

A book's path is relative to the site root, and it has to stay inside it:
`images/catalogue.pdf`, `wp-content/uploads/catalogue.pdf`. A path that climbs out of
the site is refused rather than guessed at.

A folder of images works as well as a PDF. The files are used in natural order, so
`page-2.jpg` comes before `page-10.jpg`.

## What the server can do for you, if it has the tools

Two features use programs a host may or may not have. Both are optional: without them
the book is the same, it simply does more work in the browser.

| Tool | What it adds | Without it |
|---|---|---|
| `pdftotext` (poppler) | The document's words in the page, for search engines | The page carries a link to the document and no text |
| `pdftoppm` (poppler) or Imagick | The cover of a lightbox book, drawn once on the server | The browser downloads the whole document to draw the cover |

Nothing has to be configured. They are used if they are there.
