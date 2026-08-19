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
| 7 | hikari-flipbook | **Done 2026-08-19.** Hotspots (23), analytics, SEO layer and server-drawn cover (24) | 1 week |
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


## 16. Fixes after the first real use (2026-08-18)

- **Fullscreen showed a small book in a large dark screen.** The module's height cap was still
  applying. The cap exists to fit a book into the space a page gave it, which is the one thing
  fullscreen is not, so flipview 0.5.1 ignores maxHeight in fullscreen and inside a lightbox.
- **The page-turn sound is choosable, and extensible.** The sounds folder is read rather than
  hard-coded: dropping a recording into media/hikariflipbook/sounds offers it in the module and the
  plugin, with "a different one each turn" as the default. A choice is a filename and never a path,
  and a choice that no longer exists falls back to all of them rather than to silence. Platform
  gained mediaPath() for it, since nothing could previously see what a site had added.


## 17. Phase 4 result (2026-08-18)

The WordPress plugin installs, activates and renders on the local WordPress 7 site. A settings
screen holds the site defaults, a shortcode and a Gutenberg block both place books, and both go
through one `Books::render` so they cannot disagree about what a setting means. The block renders
on the server; its editor script is written against the globals WordPress already loads, so there
is no build step for it.

The book post type is deferred to phase 5, where the Joomla component and it are the same feature.

Three bugs, all found by installing rather than by reading, and the third is the one that matters:

1. **Asset URLs came out with `lib/` in them.** The platform worked its paths out from its own
   file, and the build copies the shared core into `lib/`. It reads the plugin's entry constant now.
2. **`wp_script_add_data($handle, 'type', 'module')` does not reach the tag.** The bundle is an ES
   module, so the browser stopped at the first import and nothing mounted. There is a
   `script_loader_tag` filter for it; `wp_enqueue_script_module()` would be the clean answer but it
   arrived in 6.5 and the plugin supports 6.4.
3. **A book in a narrow container blew up to a million pixels wide.** flipview was forcing the
   engine's orientation by driving `minWidth` to an extreme, and the engine writes that value onto
   the element as a `min-width`. Every container tested until now happened to be wide enough to take
   the other branch. Fixed in flipview 0.5.4 by setting `minWidth` to one page width, which decides
   the same thing honestly and does no harm on the element.

Worth remembering from the same session: WordPress versions its asset URLs by the plugin version, so
a rebuilt bundle at an unchanged version is served from cache. Hard reload when testing, and do not
trust a screenshot after a rebuild without checking the file hash on disk.


## 18. Accessibility tooling (2026-08-18)

Modelled on HikaShop's `tools/a11y`, and living in flipview, because that is where the reader's
surface is: the extension's own output is a container and a cover button, and its admin forms are
the host's markup.

`flipview/tools/a11y` runs axe-core over **states rather than pages**. A flipbook is one page whose
accessibility changes as it is used, and the states worth auditing exist only after someone has
clicked: the book over the page, the book filling the screen, the moment the share button reports
back. The scanner clicks for real, which is the only way to audit fullscreen at all, and serves the
built demo itself so a run needs nothing else up. `expected.json` holds a per-rule baseline exactly
as HikaShop's does, and it is wired into CI.

Seven states, all clean, so any violation at all is now a regression. Three things it found:

- The page number field was white on a translucent tint, 3.19 against WCAG's 4.5 for text. It is a
  solid field with dark text now, themeable through two new tokens.
- The page count was dimmed to 3.76. Full strength now.
- The lightbox cover button held a picture and nothing else, so it announced as an unnamed button.
  It carries a label supplied by the host, since only the host can translate it. That gave the
  shared core its own string namespace, HIKARI_FLIPBOOK_, which the structure rules now know about.

What it does not cover: axe finds around half of WCAG. Whether the reading order makes sense,
whether a page turn is announced usefully, and whether the book can be read at all with a screen
reader are still open questions for a person with a keyboard and one. Phase 6 owns that.


## 19. Phase 5 result (2026-08-18)

A book is defined once and placed anywhere. Verified on both local sites.

- **Joomla**: `com_hikariflipbook`, a component with a books list and an edit form over its own
  table, shipped inside the package. The module and the content plugin both take a saved book.
- **WordPress**: a `hikari_book` post type with a meta box, because WordPress already knows how to
  list, edit, restrict and trash a post. The shortcode and the block both take one.
- **The core owns what a book is**: the two hosts store it differently and have to describe it
  identically. Settings on the placement win over the book's own, so one book placed twice can look
  different each time.
- **Access is enforced where the book is read**, not after. Joomla filters by view level inside the
  query; WordPress refuses anything not published unless the reader may read it. Both were checked
  by restricting a book and confirming a visitor gets nothing at all.

Three things the install taught me, all worth remembering for the next component:

1. A component whose schema updates folder is empty fails to install, and **the failed attempt
   leaves a registration behind** that makes every later attempt fail too. That second failure looks
   like the first and sends you chasing the wrong thing.
2. `services/provider.php` must ask the container for the **interfaces**, not for the service
   providers that registered them. Get it wrong and the component installs happily and then cannot
   boot at all.
3. Joomla's CLI installer reports none of this: `[ERROR] Unable to install extension` and nothing
   else. The web installer named the problem in one line. Use it when a component will not install.

### Hotspots

Still not started, and deliberately so: the hotspot editor is the second half of a feature whose
first half does not exist. flipview has no hotspot layer yet, so there is nothing for an editor to
edit. It stays on the list as its own piece of work, viewer first.


## 20. Translations (2026-08-18)

Handled, and it was not before. The viewer shipped English tooltips and English aria-labels
whatever the site's language, which is a translation gap and an accessibility one at the same time:
a screen reader was reading English to a French visitor.

`Core\Strings` maps each string the viewer says to the key a host looks up and the English it falls
back to. One table, so a host cannot answer for a string the viewer does not use, or miss one that
it does. Joomla answers from its ini files (verified with a language override: the button said
"Tourner la page"), WordPress through gettext with the English as the msgid, which is what a
translator expects to be handed.

The WordPress plugin also shipped an empty `languages` folder, so a translator had nothing to work
from. `npm run pot` writes the catalogue, 53 strings read out of the source, and a structure rule
fails the build when it has fallen behind the code or the version.

Joomla ships en-GB only, as HikaShop does; other locales come from translators.

### Language associations for books

The book table carries a language and the store filters on it, so a site can keep an English
catalogue and a French one and place each from a language-assigned module or article. That covers
the normal case.

Associations, the com_associations mechanism that lets a language switcher jump from an article to
its French twin, are **not implemented and are not proposed for v1.0**. A book is not a page a
visitor navigates to: it is embedded in something that already has a language of its own, and that
something is what a switcher moves between. The association would only earn its place if one module
had to serve every language and swap books by itself, which is a real but narrow case, and one a
site can already answer by assigning a module per language.

What the language field did need was a voice: a book that is unpublished, or not meant for this
language or this visitor, used to render nothing at all. Someone who can fix that is now told why,
while a visitor still sees nothing.

## 21. Hotspots, the plan (done, see section 23)

A hotspot is a region drawn on a page and bound to an action: open a URL, jump to another page, or
add a HikaShop product to the cart. It is what turns a PDF catalogue into a shoppable one, and it
is the strategic reason this extension exists at all.

It is two pieces of work in order:

1. **The viewer**: a layer over each page holding regions in page-relative coordinates, so they
   survive zoom, resize and the single-page layout, plus the events for a click on one. flipview
   has none of this today.
2. **The editor**: a screen that draws rectangles on a preview of a page and records what each one
   points at. That is the piece the plan filed under phase 5, and it cannot start before the first.


## 22. Phase 6 result (2026-08-18)

The differentiators, all in flipview and inherited by both packages. Released as 0.7.0, 0.8.0 and
0.9.0 and pinned into the extension.

- **The page's own text**, laid over the picture of it. This was the honest gap: a book was a stack
  of images, so there was nothing to select, nothing to find and nothing for a screen reader to
  read. Pages also announce themselves with their number and the total.
- **Search** over the document, marking hits on the page. Pages are read on demand and kept, so the
  first search of a long document costs one pass and later ones cost nothing, and a book of images
  never offers the box at all.
- **Contents and pages panel**: the document's own table of contents where it has one, its pages as
  thumbnails where it does not, in one panel. Thumbnails are painted when it is first opened.
- **prefers-reduced-motion** is honoured: the turn still happens, it stops being an animation.

The accessibility scan grew to ten states and stays clean, so the toolbar, the panel, the search and
the marks are all covered. It earned its keep twice this phase, once on a dimmed line I had just
added to the demo and once on the search state.

Bugs worth remembering:

- The text layer has to be scaled to the width a page is **shown** at, not the width it was
  rasterised at, and rescaled on every relayout. Its CSS is pdf.js's own, renamed: pdf.js writes
  positions as percentages and sizes into custom properties and leaves the arithmetic to the
  stylesheet, so hand-rolled CSS gives default-size text in roughly the right place.
- The panel set `hidden` while its own `display` rule overrode it, so it was never hidden, and it
  was built after a promise resolved, so it could not be opened before then.
- The demo needed a PDF with bookmarks and nothing on the machine could make one, so there is a
  hand-written four page sample. Its first draft drew text above the MediaBox: valid, invisible and
  baffling.

### What is still not covered

axe passes on ten states, which is the floor rather than the ceiling. Whether the reading order
makes sense, whether a page turn is announced usefully, and whether the book is genuinely usable
with a screen reader are still open questions for a person with one. The text layer is what makes
that question answerable at all; before it, the answer was simply no.


## 23. Hotspots (2026-08-19)

Done, on both hosts, and shoppable on a real HikaShop site. The three pieces:

- **The viewer** (flipview 0.10.0): regions in fractions of a page, so one holds its place through
  zoom, a resize and the single-page layout. Each is a real link or a real button, which is what
  makes it reachable by keyboard, openable in a new tab and announceable by name. It also happens
  to be why a click on one does not start a page turn: the engine's click forwarding already
  leaves links and buttons alone.
- **The editor**: one script both admin sides mount, a form field on Joomla and a meta box on
  WordPress. Draw with the pointer, or type the numbers, which is what makes it usable from a
  keyboard at all.
- **The shop**: a region that names a product becomes a link to that product, resolved on the
  server. `Shop` is its own interface rather than another Platform method, because a site without a
  shop is not a broken site. Joomla asks HikaShop, WordPress asks WooCommerce.

Verified end to end: a region drawn on the cover in the Joomla book screen, saved, and clicked on
the front end, landing on the HikaShop product page. On WordPress, a region drawn in the meta box
jumping the reader to page 4.

### Two bugs the browser found, and reading would not have

- **Two extensions, two copies of the core, one fatal.** The module, the content plugin and now the
  component each ship their own copy; `require_once` cannot see that a different path declares the
  same class, so the second one killed the page. Every require is now guarded by what the file
  declares, one rule checks the guards exist and another loads three copies in one request to prove
  they work. This was always latent: a page with the module and the plugin on it would have done
  the same.
- **The component's Save had never worked.** Joomla's toolbar asks the form validator whether the
  form is valid, and a validator that was never loaded does not answer no: it throws, and the button
  does nothing whatsoever. No error, no message, nothing saved.

A third, smaller: WordPress stamps every asset URL with a version constant that had drifted from
the plugin header, so an updated plugin would have served browsers the old bundle. There is a rule
for that now too.

### Still open

- Nothing adds to the cart yet. A hotspot links to the product page, which is the honest version:
  a cart button that ignores required options, stock or variants would be worse than a link. Add
  to cart is worth doing once the product actually has no options.
- The editor draws on one page at a time and has no way to copy a region to another page, which a
  catalogue with a repeated layout will want.


## 24. Phase 7 finished (2026-08-19)

The three pieces that were left, all verified on the running Joomla and WordPress sites.

**Counting.** flipview 0.11.0 reports everything a reader does through one `onEvent` hook, and the
extension turns that into a `hikari-flipbook` event on the book's own container. That happens
whatever the settings say, so a site can listen for it without asking anyone and without loading
anything. The setting only decides whether the same thing is also pushed to Google Tag Manager or
handed to gtag, both of which the page has to be loading already: nothing is fetched for this, and a
service that is not there is not an error. A handler that throws is caught, because counting a page
turn must never stop one.

**Crawlers.** A book is built by JavaScript, so a search engine saw an empty box: a catalogue was
a picture of a catalogue. The page now carries a link to the document and the words on its pages,
read with pdftotext where the host has it, cached per document by path, size and time, and pruned
so the folder cannot grow forever. It goes in `<noscript>`, deliberately: text hidden with CSS from
readers and shown to crawlers is the oldest way there is to be penalised for cloaking. A book of
images has no words, so it lists its pages as `<img>` instead, which is something a crawler can
index. A host without pdftotext gets the link and no text, which is worth having on its own.

**The cover.** A book that opens over the page showed only its cover, and the browser got that cover
by downloading the whole PDF and rendering a page of it: several megabytes on every page view, for
every reader, most of whom never open the book. Where the host can draw the picture itself, through
Imagick or pdftoppm, it is drawn once and cached. Measured on the WordPress page: no PDF and no
pdf.js worker are fetched at all now, just a 16 KB PNG.

Covers need a folder that is both writable and public, and that is the media folder on Joomla and
uploads on WordPress, so `Platform` grew a `storage()` rather than pretending the cache folder would
do: on one host it is outside the public root, and a picture nobody can fetch is not a picture.

### Judgement calls

- The text goes in `<noscript>` rather than a visually-hidden div. It is the honest version and the
  one that cannot be read as cloaking, at the cost of not being in the page for a reader who has
  JavaScript and simply wants to select the text: they have the text layer for that.
- Extraction shells out to pdftotext rather than parsing PDFs in PHP. A PHP parser handles simple
  documents and produces convincing rubbish on the rest, and rubbish text on a shop's pages is worse
  than no text.
- Only the cover is pre-rendered, not every page. Pre-rendering a whole document would remove
  pdf.js from the reader's page entirely, but it would also cost the search and the text layer,
  which are worth more.

### Still open

- The cover is drawn at 640px and shown at 320: fine for a thumbnail, not for a poster on the
  inline book, which still shows nothing until pdf.js has painted page one.
- Nothing invalidates a cover or a text cache when the document is replaced *in place* with the same
  size and timestamp, which is rare enough to live with and impossible to detect cheaply.


## 25. Updates: GitHub is the update server (2026-08-19)

No server to run, and none to keep alive for as long as the extension is installed anywhere.

- `updates/pkg_hikariflipbook.xml` is generated by `build/update.mjs` from package.json and committed.
  Joomla reads it raw from the default branch; the package manifest points there.
- The download it names is the release asset for the matching tag, and `.github/workflows/release.yml`
  builds that asset on a `v*` tag, refusing to publish if the tag and package.json disagree.
- Four structure rules keep the chain honest: the offered version is the built version, the element
  and type are the package's own, the download URL names a file this build actually produced, and the
  manifest points at the same URL the generator writes. CI also fails if the committed update file
  has drifted from package.json.

Verified against the running Joomla 6 site, with the update site pointed at a local copy offering
0.3.0: Check For Updates found it and the Updates screen offered "Hikari Flipbook, Site, Package,
installed 0.2.0, available 0.3.0".

**The bug that test caught.** Without `<client>site</client>` Joomla's extension adapter defaults the
client to *administrator*. The update is then found, stored with `extension_id = 0`, matched against
no installed extension, and never shown to anyone. Nothing errors, nothing warns, and the Updates
screen simply stays quiet forever. Reading the XML back would never have shown it.

### WordPress: decided (2026-08-19)

Updates come from the wordpress.org listing, and we build nothing for it. A plugin installed by hand
does not update itself, so the README and the installing page point at the releases page for the
manual download and say so plainly. One less mechanism to own, and no risk of a self-updater
fighting the directory's once the plugin is listed.


## 26. Documentation (2026-08-19)

`docs/` in the repository, Markdown, readable on GitHub as it stands and ready to be a Pages site
later:

| Page | For |
|---|---|
| README | What it is, what it does not do, and a working book in three steps |
| installing | Both hosts, what the Joomla package contains, updates, and the two optional server tools |
| placing-a-book | Module, `{flipbook}`, shortcode, block, and the book manager on both hosts |
| options | Every setting in one table, plus the analytics event and the CSS custom properties |
| hotspots | Drawing them, what a region can be bound to, and how products resolve |
| translations | Overrides on Joomla, the .pot on WordPress, and a book per language |
| troubleshooting | The failures we have actually seen, and what each one means |

The repository README was rewritten with it: it had gone stale enough to say hotspots and the
accessibility pass were not written, and to name a Joomla artefact the build stopped producing.

**A rule keeps the options page honest**, in both directions: every setting in `Config::DEFAULTS`
has to appear in `docs/options.md`, and every setting the page documents has to still exist.
Documentation drifts silently otherwise, and the failure mode is a merchant reading about a setting
that does nothing. Both directions were tested by breaking them.

Not written, deliberately: a tutorial with screenshots. Screenshots of an admin screen age badly and
are the first thing to rot; the pages describe where things are instead. That decision is worth
revisiting once the JED and wordpress.org listings need images anyway.


## 27. The site (2026-08-19)

`site/` holds the presentation page and one stylesheet; `build/build-site.mjs` renders it together
with `docs/*.md` into `dist/site`, and `.github/workflows/pages.yml` publishes that to GitHub Pages
on every push to main. The documentation has one source: the Markdown the repository shows is the
Markdown the site renders, with `.md` links rewritten to `.html` on the way.

The page carries the version from package.json, so it cannot claim an old one, and the download
buttons point at `releases/latest`, which never needs updating.

Three rules keep it honest: the version was filled in and matches the build, every `docs/*.md` was
rendered, and no Markdown link survived into the HTML. All three were tested by breaking them.

Two screenshots, taken from a real book: a two-page spread with the toolbar, and a spread with the
hotspot regions outlined. The marketplace logo could not be the site's header mark, because it is
drawn on a hard white background and the site has a dark theme: the header uses the icon plus the
name in text, which follows the theme.

**The live demo** (added the same day) is a real book, not a recording: `site/demo.html` carries the
same container and the same JSON payload the PHP writes, and the site ships the same
`media/js/flipbook.js` both packages do. It has hotspots outlined, sound, search, download and deep
links, so the whole thing can be tried before anything is installed.

Rules cover it too, including one that parses the demo's payload. That failure is not theoretical:
a payload that does not parse leaves the container empty and says nothing at all, which is precisely
how it would reach the site unnoticed.

`npm run site:serve` builds the site and serves it on localhost:4173, so it can be looked at before
anything is pushed.


## 28. Two bugs in the contents and pages button (2026-08-19)

Both reported from the demo, both real, fixed in flipview 0.11.1.

**No icon.** The glyph is three horizontal lines, a stroked path, and the stylesheet filled it: a
filled line has no area, so the button was there, named, focusable and clickable, with nothing drawn
in it. Four icons had explicit stroke rules and the rest inherited a fill; the panel was the one
nobody remembered to add. Stroking is now the default and the four arrow glyphs are the exception,
which is the safer way round: a new stroked icon works without anyone remembering anything.

**The panel pushed the toolbar down the page.** Its cap was `max-height: 100%`, a percentage against
a flex line whose height is set by the panel itself, so it resolved to nothing: twelve thumbnails
made the stage taller than the book and the toolbar went with it. The viewer now hands the panel the
book's height in pixels whenever it sizes the book, and the list scrolls inside that.

The fix was briefly worse than the bug: the first version called `panel.fit()` from `size()`, which
runs while the panel is still being built, so naming the const threw and the book never appeared at
all. It goes through a variable that starts null.

A unit test covers the cap. Nothing covers the missing icon, because the failure was visual and the
markup was correct throughout: inverting the default is the guard.


## 29. The panel, again, and two books on one page (2026-08-19)

**Marking and scrolling** (flipview 0.11.2). Three faults behind one report:

- Only the page the engine counts a spread from was marked, so a reader looking at pages 4 and 5
  saw one of them marked. The viewer now works out which pages are on show, using the same pairing
  rule the binding uses, and the panel marks all of them.
- The list grew as the thumbnails painted, so a scroll made while it was short left the marked page
  far below. Every thumbnail reserves its box before its picture exists, and the first page that
  paints tells the rest what shape they are.
- The scroll itself never ran. `scrollTo({behavior: "smooth"})` is an animation the browser is free
  to drop, and at least one drops it: the call was made, the list moved eleven pixels, and stopped.
  It is assigned now.

The middle one is the interesting one: the code was correct, the timing was not, and the symptom
("no auto scroll") pointed at the feature rather than at the layout underneath it.

**Two books on one page.** The URL belongs to the page. Two books both tracking `page` overwrite
each other's number on every turn, and both jump to the same page when the link is opened. Two
halves to the fix:

- flipview refuses a parameter another book on the page already claimed, warns which one, and simply
  does not track. Fighting silently is the worse failure.
- The extension gives every book after the first its own: `page`, then `page2`, `page3`. The first
  keeps the plain name so an ordinary page has an ordinary link. Verified on a real page carrying
  two books.

The trade-off is written down in the documentation: "first" means render order, so on a page whose
modules come and go, a shared link can name a different book tomorrow. The alternative, naming the
parameter after the element, makes every ordinary link ugly to save a rare case.


## 30. A scanned document was blank, and nobody would have told us (2026-08-19)

Putting a real document in the demo found the worst bug of the project so far.

pdf.js decodes JPEG 2000 and JBIG2 in wasm that it fetches at render time, and those two are what a
scanner produces: almost every scanned catalogue is one or the other. We shipped the worker and not
the decoders, so a scan rendered as blank white pages, with nothing in the console and nothing for a
merchant to report beyond "it does not work". Both packages, the hotspot editor and the site now
carry `pdfjs-dist/wasm/`, and a rule checks the site does.

flipview grew `wasmUrl` and `cMapUrl` for this, both normalised to end in a slash: pdf.js throws
"Invalid factory url" otherwise, and that kills the whole document rather than one picture in it.
A host naming a folder should not have to know that.

**Worth remembering**: the generated sample was born-digital, so every test until now used the one
kind of document that could not expose this. A demo made of real material is not decoration.

## 31. The demo document (2026-08-19)

Twelve pages of *P.L.C. Shepherd & Son's catalogue of seeds & plants*, Sydney, 1900, from the
Internet Archive: public domain (CC PDM 1.0), 1.1 MB after taking the cover and eleven illustrated
pages with `pdfseparate` and `pdfunite`. The engraving pages carry names and prices, so the hotspots
sit over things that were actually for sale, which is the product's own use case rather than a
coloured rectangle.

Candidates weighed: NASA's e-books are born-digital with clean text and beautiful, but 80 MB for one
of them, and using a public body's publication as a shop-window demo invites the "implies
endorsement" question. Standard Ebooks publish no PDF. The scan's text is OCR of 1900 type, so
search finds what the machine could read: the demo page says so rather than pretending.


## 32. Formats: the path to EPUB (2026-08-19)

Decided: **fixed-layout EPUB first, reflowable after**, with the contract grown in that order so the
second is an addition rather than a rewrite. The design is in flipview's own repository,
[FORMATS.md](../../flipview/FORMATS.md), because it is the viewer's architecture rather than the
extension's.

Done now, before any EPUB code:

- `src/source.ts` is the contract and nothing else, with no imports at all. PDF and images moved to
  `src/sources/`, one file each.
- A layering check runs in `npm test` and fails if a format library, or a format's name, appears
  outside `src/sources`. Asserted boundaries rot; this one is checked. Proven by breaking it.

The four steps, in order, and why that order:

1. **Pages that are documents.** A source gains `mount()` beside `render()`, so a page can be live
   DOM in an iframe rather than a picture. Thumbnails degrade to the page number, which the panel
   already does when it cannot get a preview.
2. **Locators.** `locate(index)` and `find(locator)`: where the reader is in the document's own
   terms, a page number for PDF and a CFI for EPUB. Deep links, the panel and search all go through
   it. Small while only fixed-layout exists, and impossible to retrofit quietly once anything stores
   a page number.
3. **A page count that changes.** `layout(box)` returns the count for a page of that size; the
   viewer's relayout rebuilds the shells and restores the reader through step 2. Pagination itself
   stays inside the source: a hidden iframe per section, CSS multi-column, a page is a column offset.
4. **What stops making sense.** Hotspots are page-relative regions, so they belong to fixed-page
   documents and the host should not offer them on a reflowable book. Search stays but locates hits
   rather than numbering them.

Library: **foliate-js**, MIT, no hard dependencies, EPUB plus MOBI, AZW3, FB2 and CBZ, and it hands
over the spine, the contents and CFIs without rendering. It comes in as an optional peer dependency
exactly as pdfjs-dist does: a site that never opens an EPUB should not download an EPUB reader.

**The cost worth stating.** The flip engine draws the underside of a fold by cloning the page
element. A cloned canvas is blank, which is why a rendered page is also set as a background image; a
cloned iframe is worse. A mounted page therefore folds without its own picture on the back, unless
the source can also produce a raster, which for the common fixed-layout page (one image with text
over it) it can.


## 33. EPUB, all four steps (2026-08-19)

Done, in the order the design set out, and the order turned out to matter every time.

**Step 1, pages that are documents.** A source can paint a page as live DOM as well as into a
canvas. A fixed-layout page that is one picture is mounted as that picture rather than an iframe: it
clones for the fold, draws as a thumbnail, and is what the book actually is. Everything else gets a
frame.

**Step 2, locators.** The deep link stores what the document calls a place. For a PDF that is still
a page number, so ordinary links are unchanged; for a book that reflows it is a section and how far
through it. Finding a place became asynchronous, and that bit immediately: the first page turn wrote
its own place over the incoming link before the lookup came back, so the link is now read before
anything can overwrite it.

**Step 3, a page count that changes.** `layout(box)` returns the count for a page of that size. The
viewer rebuilds shells and engine and puts the reader back by locator. Sections are laid out as CSS
columns the width of a page, in a frame off-screen; a page is a column, slid into view with a
transform.

**Step 4, what stops making sense.** Hotspots are refused on a document that reflows, with a
sentence saying why. A search hit is reported against the page its chapter starts on rather than
against all forty pages of it.

Both hosts accept `.epub` now, verified on the running Joomla site with a comic and a novel.

### Four bugs, all of them worth the record

- **A new iframe already has a document**, a blank one, and it is complete. The column stylesheet
  went into the document the real content then replaced, so every chapter measured as one page.
- **Two pages of one chapter cannot share one frame.** Measuring uses one per section, showing uses
  one per page.
- **Laying out changes the book, which asks for a layout.** A source that takes its page shape from
  the box it is offered repaginates for ever. The shape is the source's to state.
- **An element with hidden overflow accepts a scrollLeft and ignores it**, which looks exactly like
  every page being page one. Transform instead.

### And one about me

The extension's asset build had been failing for several minutes without my noticing, because I was
running `npm run build >/dev/null 2>&1` and reading the structure check, which was happily checking
the previous build's files. Same family as the `php tests/CoreTest.php | tail -2` trap from phase 2:
hiding output hides failure. Do not redirect a build's output away.

### Still open

- A reflowable book in a narrow column paginates into hundreds of very small pages, which is
  correct and unpleasant. A minimum sensible page width, or steering such books towards the lightbox,
  is the obvious next thing.
- The site demo is still the seed catalogue only; an EPUB demo would show this off.


## 34. Four kinds of book, and the demo that shows them (2026-08-19)

The demo now offers a PDF, an EPUB, a folder of images and a folder of HTML pages, each on its own
URL. The page writes the container and the payload the Joomla module would write, so what runs below
it is the extension rather than an imitation of it.

**HTML pages are the new fourth kind**, and they cost almost nothing: one file per page, in an
iframe with a `src`. There is no archive to unpack and nothing to rewrite, because the site already
serves the pages and every relative reference resolves the way it always would. What they buy over a
PDF is that the pages are alive: real text, working links, and a page edited the way any other page
on the site is edited. They exist because step 1 of the EPUB work built `mount()`; before that, a
page had to be a picture.

Both hosts read a folder of them. A folder holding pictures is still a book of pictures, and a
stylesheet sitting beside the pages is left alone rather than treated as a page.

The demo documents: the 1900 seed catalogue as a PDF and again as six JPEGs, *La Page Blanche* (CC
BY-SA 3.0, from the IDPF samples) as a fixed-layout EPUB, and four HTML pages written for it. Each
one is credited in the footer of the page that shows it.


## 35. Shops, and how loudly to talk about them (2026-08-19)

**HikaShop runs on WordPress.** The WordPress platform asked WooCommerce and nothing else, so a
HikaShop-on-WordPress site would have drawn hotspots that linked nowhere: our own users, missed
because the Joomla side was written first and the WordPress side was written as its mirror rather
than from the same question. It asks HikaShop first now, through `hikashop_completeLink` so the URL
comes from the shop rather than being guessed at, and falls back to WooCommerce. Verified on the
running WordPress site.

**Tone.** The documentation had hotspots as the reason the extension exists and named HikaShop
throughout. That is our reason, not the reader's: most people putting a PDF on a page will never
draw a region. Hotspots are one feature among several now, the shops are named once and evenly, and
the site says "your shop" rather than ours. The feature is unchanged; only the volume is.
