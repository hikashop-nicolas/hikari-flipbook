<?php
/**
 * @package     Hikari.Flipbook
 * @copyright   Copyright (C) 2026 Hikari Software. All rights reserved.
 * @license     GNU General Public License version 3 or later
 *
 * A dependency-free harness: the core is plain PHP with no host behind it, which
 * is the whole point of the Platform interface, so testing it needs nothing more.
 * Run with: php tests/CoreTest.php
 */

// Everything the build puts in a package, in the order the build puts it: the
// interface first, then exceptions, then the rest. Listing files by hand is how a
// new one gets forgotten here and found by a fatal error somewhere else.
require __DIR__ . '/../src/platform/Platform.php';

$core = glob(__DIR__ . '/../src/core/*.php');
usort($core, static function (string $a, string $b): int {
    $weight = static fn (string $f): int => strpos($f, 'Exception') !== false ? 0 : 1;

    return $weight($a) <=> $weight($b) ?: strcmp($a, $b);
});
foreach ($core as $file) {
    require_once $file;
}

use Hikari\Flipbook\Core\Config;
use Hikari\Flipbook\Core\Renderer;
use Hikari\Flipbook\Core\Sounds;
use Hikari\Flipbook\Core\Source;
use Hikari\Flipbook\Core\SourceException;
use Hikari\Flipbook\Platform\Platform;

class FakePlatform implements Platform
{
    public $root;
    public $base;
    public $enqueued = [];

    public function __construct(string $root, string $base = '')
    {
        $this->root = $root;
        $this->base = $base;
    }

    public function config(string $key, $default = null) { return $default; }
    public function can(string $level): bool { return true; }
    public function translate(string $key): string { return $key; }
    public function asset(string $path): string { return '/media/' . $path; }
    public function cachePath(): string { return $this->root . '/cache'; }
    public function storage(): array { return ['path' => $this->root . '/storage', 'url' => $this->base . '/storage']; }
    public function rootPath(): string { return $this->root; }
    public function mediaPath(): string { return $this->root . '/media'; }
    public function baseUrl(): string { return $this->base; }
    public function escape(string $value): string { return htmlspecialchars($value, ENT_QUOTES); }
    public function enqueue(string $handle, string $path, string $type = 'script'): void
    {
        $this->enqueued[] = $type . ':' . $path;
    }
}

$failures = 0;
function check(string $what, bool $ok): void
{
    global $failures;
    if (!$ok) { $failures++; echo "FAIL  $what\n"; } else { echo "ok    $what\n"; }
}

// --- Config -----------------------------------------------------------------
$config = new Config([]);
check('defaults to auto mode', $config->get('mode') === 'auto');

$config = new Config(['mode' => 'nonsense']);
check('refuses an unknown mode', $config->get('mode') === 'auto');

$config = new Config(['showCover' => '0', 'rtl' => 'true', 'breakpoint' => '900']);
check('reads a host string as false', $config->get('showCover') === false);
check('reads a host string as true', $config->get('rtl') === true);
check('reads a host string as a number', $config->get('breakpoint') === 900);

$config = new Config(['breakpoint' => 99999, 'flippingTime' => -5]);
check('clamps the breakpoint', $config->get('breakpoint') === 2000);
check('clamps the flip time', $config->get('flippingTime') === 100);

$config = new Config(['unknown' => 'x']);
check('ignores unknown keys', $config->get('unknown') === null);

// --- Source -----------------------------------------------------------------
$root = sys_get_temp_dir() . '/hikari-flipbook-test';
@mkdir($root . '/images', 0777, true);
foreach (['b2.png', 'a10.png', 'a2.png'] as $name) {
    file_put_contents($root . '/images/' . $name, 'x');
}
file_put_contents($root . '/images/notes.txt', 'x');
file_put_contents($root . '/book.pdf', '%PDF-1.4');

// A site's sounds folder, including one it added itself and one that is not audio.
@mkdir($root . '/media/sounds', 0777, true);
foreach (['page-turn-1.mp3', 'page-turn-2.mp3', 'page-turn-10.mp3', 'readme.txt'] as $name) {
    file_put_contents($root . '/media/sounds/' . $name, 'x');
}

$source = Source::fromPath(new FakePlatform($root), 'book.pdf');
check('finds a PDF', $source->kind() === Source::KIND_PDF && count($source->files()) === 1);

$source = Source::fromPath(new FakePlatform($root), 'images');
check('finds images and skips other files', $source->kind() === Source::KIND_IMAGES
    && count($source->files()) === 3);
check('orders pages the way a human would', basename($source->files()[1]) === 'a10.png');

$refused = false;
try { Source::fromPath(new FakePlatform($root), '../../etc/passwd'); }
catch (SourceException $e) { $refused = true; }
check('refuses a path outside the site', $refused);

$refused = false;
try { Source::fromPath(new FakePlatform($root), 'images/notes.txt'); }
catch (SourceException $e) { $refused = true; }
check('refuses a file it cannot show', $refused);

file_put_contents($root . '/book.epub', 'PK');
$epub = Source::fromPath(new FakePlatform($root), 'book.epub');
check('reads an EPUB as an EPUB', $epub->kind() === Source::KIND_EPUB);

@mkdir($root . '/pages');
file_put_contents($root . '/pages/02.html', '<html></html>');
file_put_contents($root . '/pages/01.html', '<html></html>');
file_put_contents($root . '/pages/page.css', 'body{}');
$html = Source::fromPath(new FakePlatform($root), 'pages');
check('reads a folder of HTML pages', $html->kind() === Source::KIND_HTML);
check('puts the pages in order', str_ends_with($html->files()[0], '01.html'));
check('leaves the stylesheet out of the page list', count($html->files()) === 2);

// --- Renderer ---------------------------------------------------------------
$platform = new FakePlatform($root);
$html = (new Renderer($platform))->render(
    Source::fromPath($platform, 'book.pdf'),
    new Config(['rtl' => true]),
    'book-1'
);
check('emits a container', strpos($html, 'class="hikari-flipbook"') !== false);
check('emits the page as a URL, not a path', strpos($html, '/book.pdf') !== false
    && strpos($html, $root) === false);
check('carries the options', strpos($html, '"rtl":true') !== false);
check('names the cover button for a screen reader', strpos($html, '"open"') !== false);
check('asks the host for both assets', count($platform->enqueued) === 2);

$platform = new FakePlatform($root);
$html = (new Renderer($platform))->render(
    Source::fromPath($platform, 'book.pdf'),
    new Config(['download' => '1', 'barColour' => '#1976d2', 'pageColour' => 'javascript:alert(1)']),
    'book-3'
);
check('offers the document for download', strpos($html, '"downloadUrl"') !== false);
check('carries a valid colour as a custom property', strpos($html, '--fv-bar-bg:#1976d2') !== false);
check('drops anything that is not a colour', strpos($html, 'javascript') === false);

$html = (new Renderer($platform))->render(
    Source::fromPath($platform, 'images'),
    new Config(['download' => '1']),
    'book-4'
);
check('does not offer a folder of images for download', strpos($html, '"downloadUrl"') === false);

$html = (new Renderer($platform))->render(
    Source::fromPath($platform, 'book.pdf'),
    new Config(['toolbar' => '0']),
    'book-5'
);
check('can turn the toolbar off', strpos($html, '"toolbar":false') !== false);

$html = (new Renderer($platform))->render(
    Source::fromPath($platform, 'book.pdf'),
    new Config(['sound' => '1']),
    'book-6'
);
check('hands the viewer every recording it found', substr_count($html, 'sounds/') === 3);

$html = (new Renderer($platform))->render(
    Source::fromPath($platform, 'book.pdf'),
    new Config(['sound' => '1', 'soundFile' => 'page-turn-2.mp3']),
    'book-7'
);
check('uses only the chosen recording', substr_count($html, 'sounds/') === 1
    && strpos($html, 'page-turn-2.mp3') !== false);

$html = (new Renderer($platform))->render(
    Source::fromPath($platform, 'book.pdf'),
    new Config(['sound' => '1', 'soundFile' => '../../secret.mp3']),
    'book-8'
);
check('refuses a path as a choice and falls back to all', substr_count($html, 'sounds/') === 3
    && strpos($html, 'secret') === false);

$html = (new Renderer($platform))->render(
    Source::fromPath($platform, 'book.pdf'),
    new Config(['sound' => '1', 'soundFile' => 'deleted.mp3']),
    'book-9'
);
check('falls back to all when the choice is gone', substr_count($html, 'sounds/') === 3);

check('lists what the folder holds, in order',
    Sounds::available($platform) === ['page-turn-1.mp3', 'page-turn-2.mp3', 'page-turn-10.mp3']);

// A site in a subdirectory serves its files from under it: stripping the
// filesystem root alone produced a URL that missed the site entirely.
$sub = new FakePlatform($root, '/joomla6');
$html = (new Renderer($sub))->render(Source::fromPath($sub, 'book.pdf'), new Config([]), 'book-2');
check('prefixes the site base path', strpos($html, '/joomla6/book.pdf') !== false);

// --- Book ---------------------------------------------------------------------
$book = Hikari\Flipbook\Core\Book::fromRow([
    'title'  => 'Spring catalogue',
    'path'   => 'images/book.pdf',
    'params' => '{"mode":"single","sound":"1"}',
    'access' => '2',
]);
check('reads settings stored as JSON', $book->options()['mode'] === 'single');
check('keeps the access the host recorded', $book->access() === '2');
check('offers its own path', $book->merged()['path'] === 'images/book.pdf');
check('lets a caller override a setting', $book->merged(['mode' => 'double'])['mode'] === 'double');
check('ignores an empty override', $book->merged(['mode' => ''])['mode'] === 'single');

$book = Hikari\Flipbook\Core\Book::fromRow(['title' => 'Broken', 'params' => 'not json']);
check('survives unreadable settings', $book->options() === []);

// --- Strings ------------------------------------------------------------------
final class TalkativePlatform extends FakePlatform
{
    public $said = [];

    public function translate(string $key): string
    {
        $this->said[] = $key;

        // A host that knows one string and not the others, which is the normal
        // state of a half-translated site.
        return $key === 'HIKARI_FLIPBOOK_NEXT' ? 'Page suivante' : $key;
    }
}

$talkative = new TalkativePlatform($root);
$words = Hikari\Flipbook\Core\Strings::viewer($talkative);
check('uses what the host says', $words['next'] === 'Page suivante');
check('falls back to English for the rest', $words['first'] === 'First page');
check('asks for every string the viewer says', count($talkative->said) === count($words));
check('names the cover button too', isset($words['open']));


// --- Hotspots -----------------------------------------------------------------
use Hikari\Flipbook\Core\Hotspots;

$spots = Hotspots::decode('[{"page":"2","x":"0.1","y":0.2,"width":0.3,"height":0.4,"href":"/shop/kettle","label":"Blue kettle"}]');
check('reads a stored hotspot', count($spots) === 1);
check('keeps page numbers as numbers', $spots[0]['page'] === 2);
check('keeps coordinates as fractions', $spots[0]['x'] === 0.1);
check('keeps a relative link', $spots[0]['href'] === '/shop/kettle');

$spots = Hotspots::decode([['page' => 0, 'x' => -3, 'y' => 5, 'width' => 9, 'height' => 0.2, 'goToPage' => 4]]);
check('clamps a region to the page', $spots[0]['x'] === 0.0 && $spots[0]['y'] === 1.0 && $spots[0]['width'] === 1.0);
check('keeps a page jump', $spots[0]['goToPage'] === 4);

check('drops a region with no area', Hotspots::decode([['page' => 0, 'width' => 0, 'height' => 0.5, 'href' => '/a']]) === []);
check('drops one bound to nothing', Hotspots::decode([['page' => 0, 'width' => 0.5, 'height' => 0.5]]) === []);
check('survives unreadable hotspots', Hotspots::decode('not json') === []);
check('survives a stored object rather than a list', Hotspots::decode('{"x":1}') === []);

$dangerous = [
    'javascript:alert(1)',
    "java\nscript:alert(1)",
    'JaVaScRiPt:alert(1)',
    'data:text/html;base64,PHNjcmlwdD4=',
    'vbscript:msgbox',
    '//evil.example.com/',
];
$blocked = true;

foreach ($dangerous as $href) {
    $spot = Hotspots::decode([['page' => 0, 'width' => 0.5, 'height' => 0.5, 'href' => $href, 'label' => 'x', 'data' => ['a' => 'b']]]);
    $blocked = $blocked && !isset($spot[0]['href']);
}

check('refuses a link a browser would run', $blocked);
check('keeps an ordinary link', Hotspots::decode([['page' => 0, 'width' => 0.5, 'height' => 0.5, 'href' => 'https://example.com/a?b=1#c']])[0]['href'] === 'https://example.com/a?b=1#c');
check('opens in a tab only when asked', !isset(Hotspots::decode([['page' => 0, 'width' => 0.5, 'height' => 0.5, 'href' => '/a', 'target' => 'top']])[0]['target']));

$spots = Hotspots::decode([['page' => 0, 'width' => 0.5, 'height' => 0.5, 'data' => ['product' => 42, 'bad key!' => 'x', 'nested' => ['no']]]]);
check('keeps the host its own data', $spots[0]['data']['product'] === '42');
check('drops data a name could not survive', !isset($spots[0]['data']['bad key!']) && !isset($spots[0]['data']['nested']));

check('writes back what it read', Hotspots::decode(Hotspots::encode($spots)) == $spots);
check('writes an empty list rather than an object', Hotspots::encode([]) === '[]');

$config = new Hikari\Flipbook\Core\Config([
    'hotspots' => '[{"page":0,"x":0.1,"y":0.1,"width":0.2,"height":0.2,"href":"/a"},{"page":0,"width":0,"height":0,"href":"/b"}]',
]);
check('hands the viewer only the hotspots that survived', count($config->toViewer()['hotspots']) === 1);
check('says nothing about hotspots when a book has none', !isset((new Hikari\Flipbook\Core\Config())->toViewer()['hotspots']));
check('takes hotspots a host already decoded', count((new Hikari\Flipbook\Core\Config(['hotspots' => [['page' => 1, 'width' => 0.5, 'height' => 0.5, 'goToPage' => 2]]]))->toViewer()['hotspots']) === 1);

// --- two books on one page ----------------------------------------------------
check('the first book keeps the plain page parameter', Renderer::deepLinkName(true, 1) === true);
check('a second book gets its own', Renderer::deepLinkName(true, 2) === 'page2');
check('and a third', Renderer::deepLinkName(true, 3) === 'page3');
check('a book that tracks nothing still tracks nothing', Renderer::deepLinkName(false, 2) === false);
check('a name the site chose is left alone', Renderer::deepLinkName('catalogue', 3) === 'catalogue');

$platform = new FakePlatform($root);
$rendered = [];
foreach (['one', 'two', 'three'] as $id) {
    $html = (new Renderer($platform))->render(
        Source::fromPath($platform, 'book.pdf'),
        new Config(['deepLink' => '1']),
        $id
    );
    preg_match('/"deepLink":([^,]+)/', $html, $found);
    $rendered[] = $found[1];
}

check('no two books on a page track the same parameter', count(array_unique($rendered)) === 3);

// --- the cover the server draws -----------------------------------------------
$platform = new FakePlatform($root, '/site');
$lightbox = (new Renderer($platform))->render(
    Source::fromPath($platform, 'book.pdf'),
    new Config(['lightbox' => '1']),
    'book-cover'
);
// The stub PDF in this test is not a document any renderer can read, so what is
// checked is that the page still renders and simply says nothing about a cover.
check('never claims a cover it could not draw', strpos($lightbox, '"cover"') === false);
check('still shows the book', strpos($lightbox, '"lightbox":true') !== false);
check('asks for no cover unless the book opens over the page', strpos(
    (new Renderer(new FakePlatform($root)))->render(
        Source::fromPath(new FakePlatform($root), 'book.pdf'),
        new Config([]),
        'book-nocover'
    ),
    '"cover"'
) === false);

// --- what a crawler sees ------------------------------------------------------
$platform = new FakePlatform($root);
$seoHtml = (new Renderer($platform))->render(
    Source::fromPath($platform, 'book.pdf'),
    new Config([]),
    'book-seo'
);
check('puts a link to the document in the page', strpos($seoHtml, '<noscript>') !== false
    && strpos($seoHtml, 'Open the document') !== false);

$platform = new FakePlatform($root);
$seoOff = (new Renderer($platform))->render(
    Source::fromPath($platform, 'book.pdf'),
    new Config(['seo' => '0']),
    'book-seo-off'
);
check('says nothing when the site turned it off', strpos($seoOff, '<noscript>') === false);

$platform = new FakePlatform($root);
$images = (new Renderer($platform))->render(
    Source::fromPath($platform, 'images'),
    new Config([]),
    'book-seo-images'
);
check('lists a picture book as pictures', substr_count($images, '<img ') >= 2);
check('names each picture by its page', strpos($images, 'Page 1 of') !== false);

$analytics = static fn ($value) => (new Hikari\Flipbook\Core\Config(['analytics' => $value]))->get('analytics');
check('keeps an analytics service it knows', $analytics('dataLayer') === 'dataLayer');
check('reads "none" as nothing', $analytics('none') === '');
check('refuses a service it does not know', $analytics('; alert(1)') === '');

// --- a shop behind the host ---------------------------------------------------
/**
 * The hotspots as they reach the browser: through the whole renderer, since the
 * shop is asked on the way out and that is the only place it happens.
 */
function renderHotspots(Platform $platform, array $spots): array
{
    $html = (new Renderer($platform))->render(
        Source::fromPath($platform, 'book.pdf'),
        new Config(['hotspots' => $spots]),
        'book-shop'
    );

    preg_match("/data-flipbook='(.*?)'>/", $html, $found);
    $payload = json_decode(html_entity_decode($found[1], ENT_QUOTES), true);

    return $payload['options']['hotspots'] ?? [];
}

final class ShopPlatform extends FakePlatform implements Hikari\Flipbook\Core\Shop
{
    /** @var array<int,string> What this visitor has bought. */
    public $bought = [];

    public function product(string $id): ?array
    {
        return $id === '42' ? ['url' => '/shop/blue-kettle', 'name' => 'Blue kettle'] : null;
    }

    public function hasBought(string $id): bool
    {
        return in_array($id, $this->bought, true);
    }
}

$spots = [
    ['page' => 0, 'width' => 0.2, 'height' => 0.2, 'data' => ['product' => '42']],
    ['page' => 0, 'width' => 0.2, 'height' => 0.2, 'data' => ['product' => '99']],
    ['page' => 0, 'width' => 0.2, 'height' => 0.2, 'data' => ['product' => '42'], 'href' => '/mine', 'label' => 'Mine'],
];

$shopped = renderHotspots(new ShopPlatform($root), $spots);
check('turns a product into a link', $shopped[0]['href'] === '/shop/blue-kettle');
check('names the region after the product', $shopped[0]['label'] === 'Blue kettle');
check('leaves a product the shop does not know', !isset($shopped[1]['href']));
check('never overrides a link the site typed', $shopped[2]['href'] === '/mine' && $shopped[2]['label'] === 'Mine');

$plain = renderHotspots(new FakePlatform($root), $spots);
check('leaves hotspots alone on a host with no shop', !isset($plain[0]['href']));

// --- a book only its buyers may read -----------------------------------------

/** The whole rendering, since the gate has to come before anything is written. */
function renderBought(Platform $platform, string $bought): string
{
    return (new Renderer($platform))->render(
        Source::fromPath($platform, 'book.pdf'),
        new Config(['bought' => $bought]),
        'book-bought'
    );
}

$buyer = new ShopPlatform($root);
$buyer->bought = ['42'];

check('shows the book to whoever bought it', strpos(renderBought($buyer, '42'), 'data-flipbook') !== false);
check(
    'shows it when any one of several was bought',
    strpos(renderBought($buyer, '7,42'), 'data-flipbook') !== false
);

$stranger = new ShopPlatform($root);
$locked   = renderBought($stranger, '42');

check('never writes the document for someone who did not buy it', strpos($locked, 'data-flipbook') === false);
check('says the book is for buyers, and where to buy it', strpos($locked, '/shop/blue-kettle') !== false);
check(
    'says only that much when the shop cannot name the product',
    strpos(renderBought($stranger, '99'), 'buyers') !== false
        && strpos(renderBought($stranger, '99'), '<a ') === false
);
check(
    'shows nothing at all on a host with no shop to ask',
    renderBought(new FakePlatform($root), '42') === ''
);
check('leaves a book with no product alone', strpos(renderBought($stranger, ''), 'data-flipbook') !== false);

echo $failures === 0 ? "\nall good\n" : "\n$failures failing\n";
exit($failures === 0 ? 0 : 1);
