# Hikari Flipbook

A free flipbook for **Joomla and WordPress**: it shows a PDF, or a folder of images,
as a book with turning pages. The document is rendered in the visitor's browser, so
nothing is uploaded anywhere and no service is called.

Built on [flipview](https://github.com/hikashop-nicolas/flipview), our standalone
page-flip viewer.

**[Documentation](docs/README.md)** — installing, placing a book, every setting,
hotspots, translating it, and what to do when something is wrong.

## What it does

- Real page turns, on a phone as well as a desktop, with an optional page-turn sound.
- Zoom, fullscreen, a link to the current page, and an optional download button.
- Search inside the document, its own table of contents, and thumbnails of every page.
- **Hotspots**: draw a region on a page and bind it to a link, another page, or a
  product, which turns a catalogue into one a visitor can buy from.
- The document's text in the page for search engines, and a text layer a reader can
  select from.
- Reduced motion honoured, every control reachable by keyboard and named for a screen
  reader; the accessibility scan runs in CI.

## Installing

**Joomla**: install `pkg_hikariflipbook-<version>.zip` from the
[releases page](https://github.com/hikashop-nicolas/hikari-flipbook/releases). The
package registers its own update site, so later versions arrive through
**System, Update, Extensions**.

**WordPress**: install from the plugin directory, which is also where updates come
from. To install by hand, download `hikari-flipbook-<version>.zip` from the same
releases page and upload it under **Plugins, Add New, Upload Plugin**; a plugin
installed that way does not update itself.

Joomla 4, 5 and 6. WordPress 6.4 or newer. PHP 8.1 or newer.

## One repository, two packages

```
src/
  core/        platform-free PHP: config, sources, hotspots, rendering
  platform/    the Platform interface, all a host has to provide
  joomla/      the Joomla adapter, module, plugin and component
  wordpress/   the WordPress adapter, plugin, block and book manager
  assets/      the shared front-end, bundled into both packages
build/         the two builders, the shared helpers and the rule engine
docs/          the documentation
updates/       the update file Joomla reads, generated from package.json
```

`src/core` never names a host. Everything host-specific goes through `Platform`, and
CI fails the build if a Joomla or a WordPress symbol appears in the core. That one
rule is what keeps the two packages from drifting into two codebases.

The host's own guard (`_JEXEC` for Joomla, `ABSPATH` for WordPress) is injected at
build time rather than written in source, for the same reason. So are the guards that
let two extensions load their own copy of the core in one request without a fatal
redeclare.

## Building

```sh
npm install
npm run build     # both packages into dist/
npm run check     # the structure rules, run in CI too
npm test          # the core tests, plain PHP, no framework
```

Output: `dist/pkg_hikariflipbook-<version>.zip` for Joomla, and
`dist/hikari-flipbook-<version>.zip` for WordPress. A `v*` tag builds both and
publishes them as release assets, which is what the Joomla update file points at.

## Licence

GPL-3.0-or-later. The viewer it embeds is MIT, and pdf.js is Apache-2.0; see
[THIRD-PARTY.md](THIRD-PARTY.md).
