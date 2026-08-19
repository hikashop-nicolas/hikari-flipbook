# When something is wrong

## The book does not appear at all

- **A message in its place**: the path is wrong, or the document is not where it says.
  The message is only shown to someone who could fix it, so a visitor sees nothing.
- **Nothing at all, an empty space**: the viewer did not load. Check the browser
  console for a blocked script; a CSP that forbids `worker-src blob:` stops PDF
  rendering, and one that forbids inline module scripts stops everything.
- **On Joomla, after an update**: clear the site cache. The viewer is served with a
  version stamp, but a full-page cache can outlive it.

## The pages are blank, or only some are

Pages are drawn as they are needed, so a page can be blank for a moment. If it stays
blank, the document is probably one pdf.js cannot draw: try opening it in a browser
directly. Scanned documents with unusual compression are the usual case.

## The text cannot be selected, and search finds nothing

The document has no text: it is a scan, a picture of a page. There is nothing to
select, nothing to find, and nothing for a search engine either. Running the PDF
through OCR before uploading it fixes all three at once.

## Search engines still see nothing

The words go into the page only if the server can read them, which needs `pdftotext`.
Look at the page source for a `<noscript>` block: if it holds a link to the document
and no text, the tool is missing on your host. Ask your host for the `poppler-utils`
package, or accept the link on its own.

The setting to turn this off is `seo`; it is on by default.

## The cover of a lightbox book is slow to appear

The server draws that cover if it can, with `pdftoppm` or Imagick. Without either, the
browser downloads the whole document to draw it, which is slow for a large catalogue.
Same answer as above: `poppler-utils`, or the `php-imagick` extension.

## A hotspot does nothing when clicked

- It is bound to nothing. A region needs a link, a page, or a product id.
- Its product is unpublished, deleted, or the shop is not installed, so it stayed a
  plain region on purpose rather than linking somewhere broken.

## The page turn sound does not play

Browsers refuse to play sound before a visitor has interacted with the page. The first
turn a reader makes is an interaction, so the sound starts from that turn on. If it
never plays, check that a sound file exists in the extension's `media/.../sounds`
folder.

## It is slow on a phone

- Set `maxHeight` so the book does not try to fill a tall screen.
- Use `lightbox` so the page shows a cover and loads the document only when asked.
- A 200 MB scanned PDF is a 200 MB download whatever the viewer does. Compress it, or
  export it as a folder of images and point the book at the folder.

## Reporting something

Please include the extension version, the Joomla or WordPress version, whether the
document is a PDF or a folder of images, and anything in the browser console:
[github.com/hikashop-nicolas/hikari-flipbook/issues](https://github.com/hikashop-nicolas/hikari-flipbook/issues).
