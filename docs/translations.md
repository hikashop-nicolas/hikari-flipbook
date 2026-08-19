# Translating it

Everything the viewer says comes from the host, in the site's own language: the
tooltips, the page counter, and what a screen reader hears.

## Joomla

The package ships English (`en-GB`) and takes overrides like any other extension:
**System, Language Overrides**, pick the extension, and change the key.

Keys the viewer uses start with `HIKARI_FLIPBOOK_`, for example
`HIKARI_FLIPBOOK_NEXT` or `HIKARI_FLIPBOOK_PAGE_OF`. `HIKARI_FLIPBOOK_PAGE_OF` carries
two placeholders, `{page}` and `{total}`, which have to survive translation.

A full translation is a language pack: copy `en-GB.mod_hikariflipbook.ini` to your
language's folder and translate the right-hand side.

## WordPress

The plugin's text domain is `hikari-flipbook` and it ships
`languages/hikari-flipbook.pot`. Translate it with Poedit or GlotPress and drop the
`.mo` in `wp-content/languages/plugins/`, or translate it on
[translate.wordpress.org](https://translate.wordpress.org) once the plugin is listed
there.

## A book per language

Both hosts already know how to show different content to different languages, and a
book is content:

- **Joomla**: a book has a Language field. Set one book to French and another to
  English, and each is only shown on that language's pages. A module can be assigned a
  language too.
- **WordPress**: with a multilingual plugin, a book is a post type like any other, so
  it is translated the way that plugin translates posts.

There is no separate association screen, because neither host needs one: place the
right book on the right page.
