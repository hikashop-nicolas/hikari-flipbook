# Third-party content

## flipview

- Source: https://github.com/hikashop-nicolas/flipview
- Licence: MIT
- Not vendored: a pinned dependency, bundled into `media/js/flipbook.js` at build time.
- It carries its own vendored fork of StPageFlip (MIT) and requires pdf.js
  (Apache-2.0). Both are recorded in that repository's THIRD-PARTY.md.

## fflate

- Source: https://github.com/101arrowz/fflate
- Licence: MIT
- Not vendored: a dependency, bundled into `media/js/flipbook.js`. It reads the zip
  an EPUB is.

## Page-turn sounds

`src/assets/sounds/page-turn-1.mp3` and `page-turn-2.mp3`.

- Source: Pixabay, https://pixabay.com/sound-effects/film-special-effects-turnpage-99756/
  and https://pixabay.com/sound-effects/film-special-effects-book-page-45210/
- Licence: **Pixabay Content License**. Free for commercial and non-commercial use,
  no attribution required. It forbids selling or redistributing the files on a
  standalone basis, and redistributing them on a competing stock platform. Shipping
  them inside an extension is use within a larger work, which the licence allows.
- Processed before shipping: trimmed, converted to mono, and levelled so the two
  match each other within half a decibel. Roughly 5 KB each.

**Worth knowing before submission.** The Pixabay Content License is not the GPL. The
extension's own code is GPL-3.0-or-later, and these two files are redistributable
but not relicensable, which is the usual position for bundled media. If either
directory objects, the fix is to swap them for CC0 recordings: a site chooses any
file in the sounds folder, and a book with the sound turned off asks for none of
them, so nothing depends on these two in particular.

## The demo documents

These sit in `site/demo/` and are published with the project site. None of them is
part of either package: a site that installs the extension gets none of this.

### The 1900 seed catalogue

`site/demo/catalogue.pdf` and the JPEGs in `site/demo/pages/`.

- Source: *P.L.C. Shepherd & Son's catalogue of seeds & plants and guide to gardening
  1900-1901*, https://archive.org/details/Shepherd56406
- Licence: **public domain** (Creative Commons Public Domain Mark 1.0), digitised by
  the Caroline Simpson Library, Museums of History NSW.
- Twelve pages taken from the scan with `pdfseparate` and `pdfunite`; the JPEGs are
  the first eight of those pages rendered with `pdftoppm`.

### La Page Blanche

`site/demo/comic.epub`.

- Source: the IDPF EPUB 3 samples, https://github.com/IDPF/epub3-samples
- Authors: Boulet (script) and Pénélope Bagieu (art), éditions Delcourt.
- Licence: **CC BY-SA 3.0**. Attribution is required and is given on the demo page
  that shows it; the file is redistributed unmodified.

### The HTML pages

`site/demo/html/`. Written for this demo, and covered by the project's own licence.
