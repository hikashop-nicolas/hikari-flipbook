# Hotspots

A hotspot is a region drawn on a page and bound to something: a link, another page in
the book, or a product in a shop. Most books need none of this; a catalogue usually
does.

Regions are stored as fractions of a page rather than pixels, so one stays where it was
put through zoom, a resize, and the single-page layout a phone gets.

They need a page that holds still, so they work on PDFs, on folders of images, and on
fixed-layout EPUBs. A reflowable EPUB has no such page: the words on page 12 are on
page 15 on a narrower screen, and a region drawn over them would be a region over
something else. Those books are shown without hotspots.

## Drawing them

Open a book in the manager: **Components, Hikari Flipbook** on Joomla, **Flipbooks** on
WordPress. Joomla puts them on a Hotspots tab, WordPress in a Hotspots box under the
book.

- **Drag on the page** to draw a region. Drag a region to move it, or its corner to
  resize it.
- **Add a region** puts one in the middle of the page, for working without a mouse.
  Left, top, width and height can then be typed as percentages.
- Turn pages with the arrows above the page. A region belongs to the page it was drawn
  on.
- **Delete this region** removes the selected one.

Save the book as usual.

## What a region can do

| Field | What happens when a reader uses it |
|---|---|
| Link | Goes to that address. Anything a browser follows: a page on the site, another site, a `mailto:` |
| Open in a new tab | The link opens in a new tab instead of this one |
| Go to page | Turns the book to that page |
| Product id | Links to that product in the shop |

**Name** is what a screen reader announces, and what the editor labels the region with.
Give it the name of the thing it is over: "Blue kettle, 24 euros" beats "Region 3".

A region needs one of those four to be worth anything: a region bound to nothing is
dropped when the book is saved.

## Products

A region with a product id becomes a link to that product, worked out on the server
using the shop's own link builder, so it respects your permalinks, menu items and SEF
settings. HikaShop and WooCommerce are both understood, on either platform.

If there is no shop, or the product does not exist or is unpublished, the region stays
a plain region rather than linking somewhere broken. If you filled in a link as well,
your link wins.

Because it is an ordinary link in the page, it works with the keyboard, it can be
opened in a new tab, and a screen reader announces the product's name.

It links to the product page rather than adding to the cart. A cart button that ignored
required options, stock or variants would be worse than a link.

## Showing them

Hotspots are invisible until a reader hovers or focuses one, which suits a magazine.
For a catalogue meant to be shopped, turn on **Outline the regions** so every one is
marked as soon as the book opens.

The outline colours are two custom properties, `--fv-hotspot-fill` and
`--fv-hotspot-line`; see [options](options.md#appearance).
