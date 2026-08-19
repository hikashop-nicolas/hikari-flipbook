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

Five kinds of document work:

| What | Notes |
|---|---|
| A **PDF** | The usual case. Scans work too, where the host has the tools below |
| A folder of **images** | Used in natural order, so `page-2.jpg` comes before `page-10.jpg` |
| An **EPUB** | Fixed-layout books show as they were designed; books that reflow are laid out into pages for the screen they are read on |
| A **CBZ** | A comic archive: a zip of pictures, in natural order. CBR is not read, since RAR needs a decoder we will not ship; rezip it as a CBZ |
| A folder of **HTML pages** | One file per page, in natural order. The pages are yours to edit, the text is real text and the links work |

A folder holding pictures is a book of pictures; a folder holding HTML files and no
pictures is a book of pages. Anything else in the folder, a stylesheet for instance,
is left alone.

An EPUB that reflows has no fixed pages: the number of them depends on the size of
the screen, so page 12 on a laptop is not page 12 on a phone. Links to a page still
work, because what is stored is the place in the book rather than the number of the
page. Hotspots are not offered on such a book, for the same reason.

## What the server can do for you, if it has the tools

Two features use programs a host may or may not have. Both are optional: without them
the book is the same, it simply does more work in the browser.

| Tool | What it adds | Without it |
|---|---|---|
| `pdftotext` (poppler) | The document's words in the page, for search engines | The page carries a link to the document and no text |
| `pdftoppm` (poppler) or Imagick | The cover of a lightbox book, drawn once on the server | The browser downloads the whole document to draw the cover |

Nothing has to be configured. They are used if they are there.
