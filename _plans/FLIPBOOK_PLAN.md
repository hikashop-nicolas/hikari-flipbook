# Hikari Flipbook: research + plan

Status: draft v2 for review (2026-08-18). Revision: split into a standalone JS library repo plus a
dual-build extension repo (Joomla + WordPress), both on GitHub, free, Hikari brand.

## 1. Research findings (condensed)

- **The extensionbase module cannot be reused.** Its demo CSS fingerprint (flipbook-3dcanvas,
  flipbook-hard-page, annotationLayer, customLinkAnnotation) is Real3D FlipBook, a proprietary
  CodeCanyon plugin. Its JED listing is unpublished, reason "UR8: Copyright Violations". Copying its
  JS or CSS imports exactly the violation that killed it. The documentation is fine as competitive
  research; feature ideas are not copyrightable, the code is.
- **turn.js is out too.** v3 is jQuery-bound, unmaintained, flagged non-commercial. v4 needs a paid
  licence that explicitly forbids redistribution under an open source licence, which no GPL
  extension can satisfy.
- **Market gap.** Of six flipbook extensions on the JED, four are unpublished (two for copyright,
  two for broken links). The survivors, BA Flipbook and Dearflip, are both paid. There is no free
  flipbook extension on the JED today. Same picture is worth checking on wordpress.org before
  release, but the WP side is dominated by freemium plugins with hard feature walls.
- **Stack.** pdf.js (Apache-2.0, 6.x, actively released) for rendering, StPageFlip / npm page-flip
  (MIT, no deps, canvas and HTML modes, portrait auto-switch) for the flip effect. Both permissive,
  both clean-room. No WebGL 3D bending: it is where the paid engines burn their budget and generate
  their bug reports, and a tuned CSS 3D fold looks close enough at flip speed.

## 2. Repo split

### 2.1 flipview (the library)

- Home: github.com/hikashop-nicolas/flipview, public, **MIT**, local checkout ~/dev/flipview
- Follows the existing house pattern (pdfedit, sheetedit, geoedit, mediaplay, imageview): a
  standalone client-side lib with its own demo page on GitHub Pages, consumed downstream as a
  lockfile-pinned git dependency.
- Zero knowledge of Joomla, WordPress, or PHP. It takes a source and a container, and returns a
  viewer instance.
- pdfjs-dist stays a **peer dependency**, not bundled. That keeps the Apache-2.0 code in its own
  layer so the downstream GPL build can vendor it deliberately with its own licence header.
- Public surface, roughly:

```js
const book = await Flipview.create(container, {
  source: { type: 'pdf', url },       // or { type: 'images', urls: [...] }
  mode: 'double' | 'single' | 'auto',
  zoom, sound, rtl, deepLink, toolbar: {...},
  hotspots: [...],
  onPageChange, onHotspotClick, onReady,
})
book.goTo(7); book.search('term'); book.destroy()
```

- Owns everything visual and interactive: rendering pipeline, LRU page cache, flip animation,
  zoom and pan, toolbar, lightbox, fullscreen, keyboard and ARIA, search, outline, thumbnails,
  hotspot hit-testing, deep linking, analytics event emission.
- StPageFlip is vendored into the lib (last push Jan 2024, roughly 2k lines, MIT) so we own the
  patches rather than depend on a stalled npm release.
- Ships as ESM plus a UMD build, with a CSS file and a themeable set of custom properties.

Reusable elsewhere: this is the same lib Omnitext could use for a "read as book" mode, and the same
one a future Hikari demo site or any third party can drop into a plain HTML page.

### 2.2 hikari-flipbook (the extension)

- Home: github.com/hikashop-nicolas/hikari-flipbook, public, **GPL-3.0-or-later**, local checkout
  ~/dev/hikari-flipbook
- GPL-3.0 rather than GPLv2: Apache-2.0 (pdf.js) is incompatible with GPL-2.0 but fine with GPL-3.0.
  Both the JED and wordpress.org accept GPLv3.
- Consumes flipview as a lockfile-pinned git dependency. The build vendors the built dist into the
  platform media folder. Per house rule, fixes to flipview get verified against the vendored dist in
  the consumer, not just the lib demo.
- **Two build outputs from this one repo**, with directory rules enforced in CI (section 4).

Note: HikaShop itself ships a single unified Joomla+WP package via its WP bridge. That bridge is a
large piece of machinery to carry for a small standalone module, so here two separate builds off a
shared PHP core is the cheaper shape.

## 3. Extension repo layout

```
hikari-flipbook/
  src/
    core/            platform-agnostic PHP: config resolution, source/path validation,
                     page cache, hotspot model, SEO text extraction, sanitising
    platform/        the Platform interface: config(), user(), can(), translate(),
                     asset(), cachePath(), escape(), url()
    joomla/          Joomla adapter + module + plugins
    wordpress/       WordPress adapter + plugin
    assets/          shared front-end source, the SCSS/JS glue around flipview
  assets/            public brand assets: logo, banners, icons, jed.txt (repo only, never shipped)
  build/
    build-joomla.mjs
    build-wordpress.mjs
    render-assets.sh        SVG to PNG, reproducible
    check-structure.mjs     the CI rule engine
  tests/
  _plans/                   design docs (repo only, never shipped)
  dist/                     build outputs, gitignored
```

Root assets/ holds the brand and marketplace material rather than code. That is deliberate: it is
exactly the folder name wordpress.org expects for plugin banners, icons and screenshots, so the WP
submission can point straight at it. The shared front-end source moved to src/assets/ to keep the
name unambiguous. Front-end source is code and belongs under src/ anyway.

The heart of the design: **src/core never names a platform**. Everything platform-specific goes
through the Platform interface, with two implementations. CI enforces this by grepping src/core for
Joomla and WordPress symbols and failing on any hit. That single rule is what keeps the two builds
from drifting into two codebases.

### Joomla build output

pkg_hikariflipbook.zip containing:

- mod_hikariflipbook, the renderer module (J4/J5/J6 style: services/provider.php, src/Dispatcher,
  src/Helper, tmpl/)
- plg_content_hikariflipbook, shortcode for articles
- ~~plg_fields_hikariflipbook, custom field type~~ **skipped by decision, 2026-08-18**. The
  content plugin already places a book anywhere article text goes, which covers most of what the
  field would have added
- com_hikariflipbook (phase 3), the book manager: define a book once with its settings, access
  level, and hotspots, instead of reconfiguring every module instance
- media/mod_hikariflipbook/ with joomla.asset.json, registered through the Web Asset Manager

### WordPress build output

hikari-flipbook.zip containing:

- hikari-flipbook.php with the plugin header
- a Gutenberg block plus a [hikari_flipbook] shortcode (the WP equivalents of module and content
  plugin)
- a settings screen and a custom post type for books (the WP equivalent of the component)
- media/ with the same vendored flipview dist (named media/ rather than assets/ so it matches the
  Joomla build, and so nothing collides with the repo-root brand assets/ folder)
- readme.txt and languages/hikari-flipbook.pot

## 4. CI: directory and platform rules

Every rule below is a hard failure, run against both the source tree and the extracted build zips.

**Shared core**
- src/core and src/platform contain no Joomla symbol (Joomla\, JFactory, Factory::getApplication)
  and no WordPress symbol (add_action, add_filter, wp_, get_option, ABSPATH)
- every platform call in core goes through the Platform interface, no direct superglobals
- PHPStan on core with no platform stubs loaded, so a leak cannot even typecheck

**Joomla build**
- manifest is mod_hikariflipbook.xml at package root, and the manifest file list is checked
  bidirectionally: every listed file exists, every shipped file is listed
- language files at language/en-GB/*.ini and *.sys.ini, keys uppercase and prefixed
  MOD_HIKARIFLIPBOOK_, en-GB is the only shipped locale (house rule, other locales via the
  translation server), new keys appended at the end of the file
- media assets only under media/mod_hikariflipbook/, joomla.asset.json present and valid
- every PHP file carries the JEXEC guard, exactly once
- no WordPress symbol anywhere in the tree
- phpcs against the Joomla coding standard
- version in the manifest matches package.json and the changelog

**WordPress build**
- root plugin header present, Version matching package.json, Stable tag in readme.txt matching
- ABSPATH guard in every PHP file
- text domain hikari-flipbook on every translation call, .pot regenerated and diff-clean
- every global function, class, option, and hook prefixed hikari_flipbook_ / Hikari_Flipbook_
- no Joomla symbol anywhere in the tree
- phpcs against WPCS, plus the official wordpress/plugin-check-action
- runtime files under media/, no node_modules, no source maps, no dev files in the zip

**Both**
- the vendored flipview dist hash matches the version pinned in package-lock.json, so a stale
  vendored bundle cannot ship
- THIRD-PARTY.md lists pdf.js (Apache-2.0), StPageFlip (MIT), flipview (MIT), and matches the
  lockfile; every vendored file keeps its original licence header. This is precisely what the two
  delisted JED extensions failed to do.
- reproducible build: rebuilding from a clean checkout produces byte-identical zips
- repo-only folders never appear in either build output: assets/, _plans/, build/, tests/. Worth an
  explicit rule because "assets" reads as shippable, and the Joomla builds do ship a media folder

Workflows: ci.yml on every push (lint, structure, phpstan, unit tests, build both, re-check the
zips), release.yml on tag (build both, attach to the GitHub release, publish the Joomla update XML,
publish to GitHub Pages for the demo).

## 5. Feature scope

### v1.0, parity with the dead module

PDF, image folder, and mixed sources. Single and double page with auto-switch to single on narrow
viewports. Flip animation with configurable speed, optional page-turn sound. Zoom by wheel, pinch,
double-tap, plus pan. Prev, next, first, last, page number input, keyboard arrows. Lightbox mode,
inline mode, native fullscreen. Toolbar button toggles, colour options, container height, RTL.
Deep linking to a page, share button, toggleable download button. Multilingual. J4 to J6 and
current WP, PHP 8.1+, no jQuery.

### Beyond v1.0, still free, where we beat the paid ones

1. **Real accessibility.** Keyboard-complete, ARIA book and page semantics, prefers-reduced-motion
   degrading to an instant page switch, and a plain reader fallback with selectable text. No paid
   Joomla flipbook does this properly. Headline differentiator, and it lives in flipview so both
   platforms get it for free.
2. **Search inside the document** via the pdf.js text layer, with hit highlighting and a result list.
3. **Outline / bookmarks panel** and a thumbnail strip.
4. **Per-book access control**: Joomla view access levels, WP roles and capabilities, plus an
   optional per-book password. This is adapter work, one of the few genuinely per-platform features.
5. **Hotspots**: clickable regions bound to a URL, an article, a video, or a HikaShop product with
   add-to-cart. Turns a PDF catalogue into a shoppable one, and it is the strategic reason to
   publish this at all.
6. **SEO**: extracted text as crawlable hidden HTML plus JSON-LD, so the catalogue is indexable.
7. **Server-side page cache**: if Imagick or Ghostscript is present, rasterise to WebP on first
   request. Big mobile win, and it enables a protect-source-PDF mode where the raw file is never
   downloadable.
8. **Analytics events** to GA4 or Matomo: book opened, page viewed, hotspot clicked.
9. **Bookshelf view**: several books in a grid with categories.
10. **Print current page or all**, watermark overlay.

## 6. Phases

| Phase | Repo | Work | Rough size |
|---|---|---|---|
| 0 | flipview | **Done 2026-08-18.** Spike: pdf.js plus StPageFlip, on-demand rendering, portrait mode. See section 10. | 1-2 days |
| 1 | flipview | **Done 2026-08-18**, tagged v0.1.0 and public at github.com/hikashop-nicolas/flipview | 1 week |
| 2 | hikari-flipbook | **Done 2026-08-18.** Skeleton, core plus Platform, both adapters, both builds installable, CI rules in place. Joomla package verified rendering on the local Joomla 6 site. | 3-4 days |
| 3 | hikari-flipbook | Joomla module with the full v1.0 set, content plugin, custom field | 1 week |
| 4 | hikari-flipbook | WordPress plugin: block, shortcode, settings, book CPT | 4-5 days |
| 5 | both | Book manager: Joomla component and WP CPT admin, ACL, hotspot editor | 1-2 weeks |
| 6 | flipview | Differentiators: accessibility pass, search, outline, thumbnails | 1 week |
| 7 | hikari-flipbook | SEO layer, server-side cache, analytics, HikaShop product hotspots | 1 week |
| 8 | both | Release: JED submission, wordpress.org submission, update server XML, docs, demo site | few days |

CI rules land in phase 2, before there is any real code to violate them. Retrofitting a
platform-free core after the fact does not work.

## 7. Risks

- StPageFlip is stalled. Mitigated by vendoring it into flipview and owning the fork.
- pdf.js 6.x is ESM and drops older browsers. Decide the browser baseline in phase 0.
- Large scanned PDFs are memory-hungry client-side until the phase 7 server cache exists. Cap
  concurrent rendered pages in flipview from the start.
- wordpress.org review is slower and stricter than the JED. Running plugin-check in CI from phase 2
  avoids a late scramble.
- Naming: "flipbook" is heavily used. Check for trademark conflicts on both flipview and
  hikari-flipbook before either submission.

## 8. Open points

1. GitHub home: keep both under the existing hikashop-nicolas account like the other libs, or
   create a Hikari Software org and move them there? An org is easier to justify for a
   Hikari-branded free extension.
2. Does flipview get adopted by Omnitext as a "read as book" mode, or stay standalone for now?

## 9. Brand assets

Sources live in assets/ as SVG, every PNG is generated by build/render-assets.sh (rsvg-convert), so
the images are reproducible and never hand-edited. The folder is public repo material and is
excluded from both build outputs. Style follows the existing HikaShop plugin _doc
convention: blue #1976D2 / gradient #1977D3 to #1F8EEB, soft white circles, Helvetica bold.

| File | Size | Used for |
|---|---|---|
| icon.svg | square, 100 unit grid | master glyph, an open book with a page mid-turn |
| logo.svg / logo.png | 884x344 | house logo size, JED listing, docs, website |
| banner.svg / banner.png | 1200x525 | house banner size, JED listing, marketplace |
| banner-wp.svg | 1544x500 | wordpress.org header, wider layout |
| banner-1544x500.png, banner-772x250.png | wordpress.org spec | plugin page header, both required sizes |
| icon-256x256.png, icon-128x128.png | wordpress.org spec | plugin icon, both required sizes |
| icon-512.png | 512x512 | favicon, demo site, docs |
| jed.txt | text | JED listing copy: Features, Installation, Use |

Still to produce, and they need the product to exist first:

- assets/screenshot.png, the JED screenshot
- assets/description.txt, the marketplace description with the changelog block (the HikaShop
  pre-commit hook expects a version entry in it, worth mirroring here)
- readme.txt for wordpress.org, with the Stable tag kept in sync by CI
- screenshot-1.png and friends for the wordpress.org assets folder

jed.txt is written against the v1.0 scope in section 5, so it describes features that are planned
and not yet built. It needs a truth pass before submission: anything not shipped comes out.


## 10. Phase 0 result (2026-08-18)

The spike lives at ~/dev/flipview, MIT, committed locally, not yet pushed. TypeScript library
plus a vite demo, same shape as geoedit and mediaplay. Verified in the browser: a generated
12-page PDF renders as a two-page spread, pages paint on demand, and the book drops to a single
page when the container goes below the breakpoint.

What the spike settled:

- **The stack works.** pdf.js paints into a canvas, the canvas goes into a StPageFlip HTML page,
  and the fold interaction is the library's problem rather than ours. Demo build is 50 kB plus
  416 kB of pdf.js, gzipped 13 kB plus 123 kB.
- **Lazy rendering is straightforward.** Page shells are created up front and stay; only canvases
  come and go, in a window of current-2 to current+3 with an LRU of 8. Page count does not drive
  memory, which is what makes long scanned PDFs survivable before the server-side cache exists.
- **The single-page breakpoint is a setting, not a rebuild.** StPageFlip's stretch mode already
  switches to portrait when the block is narrower than minWidth * 2, so 'auto' just sets
  minWidth to breakpoint/2. Destroying and re-creating the engine on resize is unnecessary.
- **The stretch layout reads the block height**, so the book element needs an explicit height that
  follows the orientation. Without it the portrait book keeps the landscape height and floats in
  a tall empty box. A ResizeObserver recomputes it and re-renders the canvases once the book has
  grown past 1.25x, so pages do not stay blurry after a resize.
- **pdf.js 5.x changed the render signature**: RenderParameters now requires `canvas`, and
  `canvasContext` alone no longer typechecks.
- **Environment trap.** Attaching the automation debugger to a tab breaks pdf.js worker messaging:
  getDocument resolves, then every getPage hangs forever with no error. Cost an hour of chasing a
  non-bug. Verify browser work in a fresh tab with screenshots only.

Not yet built in flipview, in rough order: toolbar, zoom and pan, lightbox and fullscreen, deep
links, keyboard and ARIA, text layer, search, outline, thumbnails, hotspots, theming tokens.


## 11. Phase 1 notes

Two defects the user caught by looking at the real animation, both worth remembering:

- **The fold underside was blank.** The flip engine clones a page element to draw the back of a
  folding sheet, and cloneNode never carries a canvas bitmap, so mid-turn there was an empty band
  between the pages. Each page now also carries its picture as a background image, which does
  survive cloning, with the canvas kept on top for normal display.
- **The band down the middle of the spread was an upstream positioning bug**, found from a DevTools
  dump: the hard-page path puts a left-hand page at x=0 of its block instead of at the book's left
  edge, so any leftover width in the block pushed the two halves apart. The block is now sized to
  exactly the spread, which removes the margin the bug depends on. It cost a detour through
  showCover, which was suspected first and turned out to be innocent; it is the default again.
  Owning this properly is an argument for vendoring StPageFlip sooner rather than later.

More automation-Chrome traps, same family as the pdf.js worker one in section 10. In an occluded
automation window: requestAnimationFrame is starved, so flip animations complete instantly and look
like a missing animation; canvas.toBlob never fires its callback; and canvas readback comes back
blank even though the canvas paints correctly on screen. Any of the three reads as an application
bug. Browser-verify in a fresh tab, and when something async never completes, suspect the window
before the code.


## 12. StPageFlip upstream survey (2026-08-18)

The engine is now vendored in flipview at `src/engine` (master ab30ecc, MIT). Upstream: 840 stars,
183 forks, last push January 2024, 42 open issues, 4 open pull requests, and issue #54 "Mark this
project as abandoned" with 11 reactions. No fork has meaningful traction (the busiest has one star),
so there is nothing better to base on and the maintenance is ours.

### Taken already

| Source | What | Why |
|---|---|---|
| ours | Hard pages pinned to the block's left edge, not the book's | The band down the middle of a turn |
| PR #30 | `flipPrev` jumps from x=10 in block space | Same bug class, open since June 2022 |
| issue #71 | `destroy()` never cancels the render loop | We create and destroy books repeatedly |
| issue #55 | `.sft__wrapper` matched nothing | Typo, those rules had never applied |
| PR #45 | Right-to-left reading | Already in our v1.0 scope |
| ours | Compiles under strictNullChecks, tests on the geometry helpers | It is maintained code now, not an import |

### Worth taking next

- **PR #46, top/bottom binding** (+310/-82, 13 files). Vertical page turns, the calendar layout.
  A real differentiator: no free Joomla or WordPress flipbook offers it. Also upstream issue #52.
  This is a port, not a patch: it transposes the layout maths (109 changed lines in Render alone),
  adds TOP and BOTTOM page orientations, threads a binding edge through the draw path, and bundles
  unrelated image trim-box work that we do not want. It is also written against a master we have
  since diverged from, in the pre-strict style. Budget it as its own piece of work, and have someone
  watch a real fold before calling it done: the resting layout can be checked from a screenshot, the
  fold geometry cannot.
- **PR #61, mirror rendering and soft covers** (+4342/-59). Too large to merge wholesale and it
  folds in a third party's fork, but the soft-cover rendering fix inside it is worth mining.

### Open issues we already answer

#12 single page mode (15 reactions) and #15 zoom on touch (9 reactions) are the two most wanted
features upstream, and flipview has both. #19 and #67 ask for TypeScript declarations, which
vendoring the source solves outright.

### Open issues worth fixing while we are in here

#66 `showNext` runs past the last spread. #59 `clickEventForward` logic, which we rely on. #10,
#17, #25 and #53 all want to disable click or swipe flipping, which is one option. #7 draws lines
around folded corners. #44 shows the same image under a cover, possibly the same clone-blank defect
we fixed on our side. #21 CSS refresh and #36 overflow.


## 13. Phase 1 result (2026-08-18)

flipview 0.1.0 is public at github.com/hikashop-nicolas/flipview, MIT, with CI (typecheck, tests,
both builds) and the demo deploying to Pages from main. It sits with the other house libraries
under the same account; moving it to a Hikari organisation later is a transfer, not a rebuild.

Shipped: PDF and image sources, lazy page rendering with an LRU, single and double page with a
container breakpoint, a toolbar with page entry and fullscreen, keyboard navigation, zoom and pan,
deep links, right-to-left reading, a lightbox, a string table for translation, and theming through
custom properties.

The vendored StPageFlip fork carries five fixes (three of them upstream's own bugs), compiles under
strictNullChecks, and has unit tests over its geometry helpers.

Deferred deliberately: vertical binding, section 12. Accessibility, search, outline and thumbnails
stay in phase 6 as planned.

Next: phase 2, the hikari-flipbook repo skeleton with the platform-free core and the CI directory
rules, which land before there is any code to violate them.


## 14. Phase 2 result (2026-08-18)

The extension repo exists at ~/dev/hikari-flipbook, GPL-3.0-or-later, committed locally and not
pushed. Both packages build from one shared core and the Joomla one has been installed on the local
Joomla 6 site and watched rendering a real PDF on the front end.

- `src/core` is platform-free PHP: config normalising, source resolution, rendering. `src/platform`
  is the interface, and each host ships one implementation of it.
- The host's own guard, `_JEXEC` or `ABSPATH`, is injected at build time rather than written in
  source. The core is the one body of code both hosts share and each spells that guard differently.
- `build/check-structure.mjs` enforces the rules from section 4 and is wired into CI. It was tested
  by breaking things on purpose: a Joomla symbol in the core and a wrong text domain both fail the
  build as they should.

Four bugs were caught by testing rather than by reading, which is the argument for having done
phase 2 this way:

1. and 2. Two places compared paths against an unresolved site root. On a symlinked root, one
   refused the site's own files and the other leaked a filesystem path into the page. Both were
   caught by the core tests as they were written, and the fix is one shared Paths helper.
3. The host adapter was copied into the package after the require list was generated, so the class
   was never loaded. A running site found it; a new rule now checks that everything in lib/ is
   required, and that the interface is required before the adapter that implements it.
4. Page URLs were built by stripping the filesystem root, which is correct only at a domain root.
   In a subdirectory install the viewer asked for a path that missed the site. Platform now has
   baseUrl(). This one is worth remembering: it looks correct on any machine that serves from a
   domain root, and most developer machines do.

Next: phase 3, the Joomla module's full v1.0 option set, the content plugin and the custom field.


## 15. Phase 3 result (2026-08-18)

The Joomla side is complete for v1.0 and installs as a package,
`pkg_hikariflipbook-0.1.0.zip`, 616 KB.

- **mod_hikariflipbook** carries the full option set: cover and rigid covers, right to left, sound,
  zoom, deep links, download, share, lightbox, per-button toolbar switches, toolbar and page
  colours, a height cap, the breakpoint and the turn duration.
- **plg_content_hikariflipbook** turns `{flipbook path="..."}` in article text into a book, with
  every module setting available as an attribute over the plugin's own defaults.
- **files_hikariflipbook** carries the viewer once into `media/hikariflipbook`, shared by both.
  Duplicating it would have shipped the PDF engine twice.
- The custom field plugin is **skipped by decision**: the content plugin already places a book
  anywhere article text goes.

All verified on the local Joomla 6 site: package installs, module renders, shortcode replaces in
place with the surrounding text intact.

### The sound

Synthesis was tried and rejected: it was a passable rustle and an unconvincing page turn. The
viewer now plays recordings only, two of them, picked between at random with a little rate
variation so a repeat does not sound like one. They ship in the package at about 5 KB each,
trimmed, mono, and levelled to match each other within half a decibel.

They are Pixabay-licensed, which is redistributable but not the GPL. See THIRD-PARTY.md: if the JED
or wordpress.org objects, swapping in CC0 recordings is a file change, since the viewer takes any
URL and ships no audio itself.

### flipview releases this phase

0.2.0 sound, download and share buttons. 0.2.1 maxHeight. 0.2.2 theme tokens settable from an
embedding page, which is why the module's colour reaches the toolbar. 0.4.0 host-supplied
recordings. 0.5.0 synthesis removed.

Next: phase 4, the WordPress plugin's block, shortcode, settings and book post type.
