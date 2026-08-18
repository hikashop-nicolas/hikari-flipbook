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

require __DIR__ . '/../src/platform/Platform.php';
require __DIR__ . '/../src/core/Config.php';
require __DIR__ . '/../src/core/Paths.php';
require __DIR__ . '/../src/core/SourceException.php';
require __DIR__ . '/../src/core/Source.php';
require __DIR__ . '/../src/core/Sounds.php';
require __DIR__ . '/../src/core/Book.php';
require __DIR__ . '/../src/core/BookStore.php';
require __DIR__ . '/../src/core/Renderer.php';

use Hikari\Flipbook\Core\Config;
use Hikari\Flipbook\Core\Renderer;
use Hikari\Flipbook\Core\Sounds;
use Hikari\Flipbook\Core\Source;
use Hikari\Flipbook\Core\SourceException;
use Hikari\Flipbook\Platform\Platform;

final class FakePlatform implements Platform
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
check('refuses a file that is not a PDF', $refused);

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

echo $failures === 0 ? "\nall good\n" : "\n$failures failing\n";
exit($failures === 0 ? 0 : 1);
