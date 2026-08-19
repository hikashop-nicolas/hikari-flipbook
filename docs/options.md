# Every setting

The same names everywhere: a Joomla module field, a `{flipbook}` attribute, a
WordPress shortcode attribute, a block field. Booleans are `1` and `0` in a shortcode
or a tag.

## The book

| Setting | Default | What it does |
|---|---|---|
| `path` | none | The document, or the folder of pages, relative to the site root |
| `book` | none | The id of a saved book instead of a path |
| `mode` | `auto` | `single`, `double`, or `auto` to choose by the width available |
| `breakpoint` | `700` | Below this container width in pixels, `auto` shows one page |
| `showCover` | on | Stand page 1 alone as a cover instead of pairing it |
| `hardCovers` | off | Make the covers rigid rather than bending |
| `flippingTime` | `700` | How long a page turn takes, in milliseconds |
| `maxHeight` | `0` | Largest height in pixels; 0 lets the book use the space it has |
| `rtl` | off | Right to left: the spine and the page order swap sides |

## What the reader can do

| Setting | Default | What it does |
|---|---|---|
| `zoom` | on | Zoom in and out, and drag a zoomed page around |
| `sound` | off | Play a page-turn recording |
| `soundFile` | random | One recording from the sounds folder, or a different one each turn |
| `deepLink` | off | Keep the page number in the address bar, so a page can be linked to |
| `share` | off | A button that copies a link to the current page |
| `download` | off | A button that downloads the original PDF |
| `lightbox` | off | Show only the cover, and open the book over the page when clicked |

### Several books on one page

The URL belongs to the page, not to a book, so books that track their page have to
take turns naming the parameter. The first book on the page uses `#page=N` and every
book after it gets its own, `#page2=N` and so on, worked out for you.

Which book is "first" is the order they are rendered in, so on a page whose modules
come and go, a link shared today may name a different book tomorrow. Where that
matters, place the books that matter first, or give one a fixed name of its own.

## Who may read it

| Setting | Default | What it does |
|---|---|---|
| `bought` | none | Show the book only to a visitor who has bought this product |

`bought="42"` is a product id from the shop the site runs, and several ids separated
by commas mean any of them: an edition sold on its own and the bundle it is also part
of both let a reader in. A visitor who has not bought it is told so, with a link to
the product; the book itself is never rendered, so the document's address is not in
the page either.

### The book can be the product's own file

A placement with `bought` and no `path` shows the file the product is sold with:
the publisher has already uploaded the PDF or EPUB to the product, and should not
have to put a second copy somewhere the web can read it.

```
{flipbook bought="42"}
[hikari_flipbook bought="42"]
```

The document is read out by the site itself, at an address that checks the purchase
again on every request, so it is never a public file. A search engine, or anyone
with the address and no order, gets nothing. For the same reason a book that is
sold never has its words put in the page for crawlers, and its cover is drawn in the
reader's browser rather than saved as a picture anyone could fetch.

Files the shop keeps somewhere else, on S3 or another server, are passed over: only
what is on this server can be read out. Name a `path` for those.

The shop decides what counts as bought. On HikaShop it is the order statuses the shop
already uses to release a file someone paid for, so a flipbook and a download become
available at the same moment. WooCommerce is asked the same question in its own terms.

A page cache serves logged-out visitors, who are never buyers, so a cached page
carries the message rather than the book. If something on the site caches pages for
logged-in visitors as well, leave a page with a buyers-only book out of it.

A site with no shop cannot tell a buyer from anyone else, so a book with `bought` set
is shown to nobody there. Access levels are a separate thing and still apply: this is
about one purchase, not about a group a visitor belongs to.

## The toolbar

| Setting | Default | What it does |
|---|---|---|
| `toolbar` | on | Off hides the toolbar completely |
| `buttonNav` | on | The previous and next buttons |
| `buttonEnds` | on | The first and last page buttons |
| `buttonPage` | on | The page number box |

Search, the contents and pages panel, zoom, fullscreen, download and share appear when
they are useful: a book of images offers no search, and download needs a PDF.

## Hotspots

| Setting | Default | What it does |
|---|---|---|
| `hotspots` | none | The regions themselves, drawn in the book manager |
| `hotspotsShown` | off | Outline every region as soon as the book opens |

See [hotspots](hotspots.md).

## Search engines and counting

| Setting | Default | What it does |
|---|---|---|
| `seo` | on | Put a link to the document, and its words, in the page for crawlers |
| `analytics` | none | Also report to `dataLayer` (Tag Manager) or `gtag` (Analytics) |

Whatever `analytics` says, every book fires a `hikari-flipbook` event on its own
container, so a site can listen for page turns without any setting at all:

```js
document.addEventListener('hikari-flipbook', (e) => {
  // e.detail: { name, book, document, ...the rest }
  console.log(e.detail.name, e.detail);
});
```

The events are `ready`, `page`, `search`, `hotspot`, `zoom`, `fullscreen`, `download`
and `share`.

## Appearance

| Setting | Default | What it does |
|---|---|---|
| `barColour` | theme | The toolbar colour, as a hex value |
| `pageColour` | white | The colour behind a page, as a hex value |

Anything finer is CSS. The viewer's own custom properties can be set on the container
or anywhere above it:

```css
.hikari-flipbook {
  --fv-bar-bg: #2e7d32;
  --fv-page-bg: #fffdf7;
  --fv-hotspot-fill: rgb(255 214 0 / 0.25);
  --fv-hotspot-line: #f9a825;
}
```
