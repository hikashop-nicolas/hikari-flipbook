# Hikari Flipbook

A free flipbook extension for **Joomla and WordPress**: it shows a PDF, or a folder
of images, as a book with turning pages. Nothing is uploaded anywhere, the document
is rendered in the visitor's browser.

Built on [flipview](https://github.com/hikashop-nicolas/flipview), our standalone
page-flip viewer.

**Status: early.** The shared core, both host adapters and both builds are in place.
The book manager, hotspots and the accessibility pass are not written yet.

## One repository, two packages

```
src/
  core/        platform-free PHP: config, sources, rendering
  platform/    the Platform interface, all a host has to provide
  joomla/      the Joomla adapter and module
  wordpress/   the WordPress adapter and plugin
  assets/      the shared front-end, bundled into both packages
build/         the two builders, the shared helpers and the rule engine
```

`src/core` never names a host. Everything host-specific goes through `Platform`,
and CI fails the build if a Joomla or a WordPress symbol appears in the core. That
one rule is what keeps the two packages from drifting into two codebases.

The host's own guard (`_JEXEC` for Joomla, `ABSPATH` for WordPress) is injected at
build time rather than written in source, for the same reason.

## Building

```sh
npm install
npm run build     # both packages into dist/
npm run check     # the structure rules, run in CI too
npm test          # the core tests, plain PHP, no framework
```

Output: `dist/mod_hikariflipbook-<version>.zip` for Joomla, and
`dist/hikari-flipbook-<version>.zip` for WordPress.

## Using it

**Joomla**: install the package, publish the module in a position, and set the path
to your PDF or image folder, relative to the site root.

**WordPress**: install and activate, then put a shortcode in a post or page:

```
[hikari_flipbook path="uploads/catalogue.pdf" mode="auto"]
```

GPL-3.0-or-later.
