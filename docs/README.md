# Hikari Flipbook

A free flipbook for Joomla and WordPress: it shows a PDF, an EPUB, a folder of
images or a folder of HTML pages as a book with turning pages. The document is rendered in the visitor's browser, so
nothing is uploaded anywhere and no service is called.

- [Installing it](installing.md)
- [Placing a book on a page](placing-a-book.md)
- [Every setting](options.md)
- [Hotspots: clickable regions on a page](hotspots.md)
- [Translating it](translations.md)
- [When something is wrong](troubleshooting.md)

## In a hurry

1. Install the package (Joomla) or the plugin (WordPress).
2. Put your PDF somewhere public: `images/catalogue.pdf` on Joomla,
   `wp-content/uploads/catalogue.pdf` on WordPress.
3. Place it:
   - Joomla, in an article: `{flipbook path="images/catalogue.pdf"}`
   - WordPress, in a post or page: `[hikari_flipbook path="wp-content/uploads/catalogue.pdf"]`

That is a working book. Everything else is a setting with a sensible default.

## What it does

- Real page turns, on a phone as well as a desktop, with an optional page-turn sound.
- Zoom, fullscreen, a link to the current page, and an optional download button.
- Search inside the document, its own table of contents, and thumbnails of every page.
- Hotspots, if you want them: regions drawn on a page that link somewhere.
- The document's text in the page for search engines, and a text layer a reader can
  select from.
- Reduced motion is honoured; every control is reachable by keyboard and named for a
  screen reader.

## What it does not do

- It does not upload, convert, or send your document anywhere.
- It does not add to the cart. A hotspot bound to a product links to that product's
  page, because a cart button that ignored options, stock or variants would be worse
  than a link.
- It does not need a licence key, an account, or a connection to us.
