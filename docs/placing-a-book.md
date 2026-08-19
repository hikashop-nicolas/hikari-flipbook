# Placing a book on a page

There are two ways to say which document to show: name a path every time, or define a
**book** once in the manager and refer to it by its id. A book keeps its own settings,
so the same catalogue can be placed in three places and look right in all three.

Whatever is written where the book is placed wins over the book's own settings, and a
book's settings win over the site defaults.

## Joomla

### In an article

```
{flipbook path="images/catalogue.pdf"}
{flipbook book="3"}
{flipbook book="3" mode="single" sound="1"}
```

The content plugin has to be enabled. Its own settings are the site defaults for every
`{flipbook}` tag.

### In a module position

**Content, Site Modules, New, Hikari Flipbook**. Pick a saved book or type a path, then
publish it in a position like any other module.

### The book manager

**Components, Hikari Flipbook**. A book has a title, a path, an access level and a
language, so a book can be shown to some visitors and not others, or in one language
only. Unpublished books are not rendered, and neither are books a visitor may not see.

The id shown in the list is what `{flipbook book="3"}` refers to.

## WordPress

### Shortcode

```
[hikari_flipbook path="wp-content/uploads/catalogue.pdf"]
[hikari_flipbook book="15"]
[hikari_flipbook book="15" mode="single" lightbox="1"]
```

### Block

Add the **Flipbook** block and fill in the book id or the path in the sidebar. Every
setting left empty follows the site default.

### The book manager

**Flipbooks** in the admin menu. Same idea as Joomla's: a book with its own settings,
placed by id. The edit screen shows the shortcode to copy.

### Site defaults

**Settings, Hikari Flipbook**. These apply to every book that does not say otherwise.
