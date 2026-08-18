# Third-party content

## flipview

- Source: https://github.com/hikashop-nicolas/flipview
- Licence: MIT
- Not vendored: a pinned dependency, bundled into `media/js/flipbook.js` at build time.
- It carries its own vendored fork of StPageFlip (MIT) and requires pdf.js
  (Apache-2.0). Both are recorded in that repository's THIRD-PARTY.md.

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
directory objects, the fix is to swap them for CC0 recordings: the viewer takes any
URL, and with none at all it synthesises the turn instead, so nothing depends on
these two files.
