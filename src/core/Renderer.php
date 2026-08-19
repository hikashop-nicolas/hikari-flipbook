<?php
/**
 * @package     Hikari.Flipbook
 * @copyright   Copyright (C) 2026 Hikari Software. All rights reserved.
 * @license     GNU General Public License version 3 or later
 */

namespace Hikari\Flipbook\Core;

use Hikari\Flipbook\Platform\Platform;

/**
 * Produces the markup for one book, and asks the host to load the viewer.
 *
 * Nothing here knows which host it is running on: the container, the options and
 * the page list are the same on Joomla and on WordPress, and everything that is
 * not the same goes through the Platform.
 */
final class Renderer
{
    /**
     * Books rendered in this request. The page number lives in the URL, and the
     * URL is one thing the whole page shares: two books both tracking "page"
     * would overwrite each other on every turn, and both would jump to the same
     * page when the link is opened.
     *
     * @var int
     */
    private static $rendered = 0;

    /** @var Platform */
    private $platform;

    public function __construct(Platform $platform)
    {
        $this->platform = $platform;
    }

    public function render(Source $source, Config $config, string $id): string
    {
        $locked = $this->locked($config);

        if ($locked !== null) {
            return $locked;
        }

        $this->platform->enqueue('hikari-flipbook', 'js/flipbook.js', 'script');
        $this->platform->enqueue('hikari-flipbook', 'css/flipbook.css', 'style');

        $urls = Paths::urls($this->platform, $source);

        self::$rendered++;

        $options = $config->toViewer();

        if (isset($options['deepLink'])) {
            $options['deepLink'] = self::deepLinkName($options['deepLink'], self::$rendered);
        }

        if (isset($options['hotspots'])) {
            $options['hotspots'] = $this->shoppable($options['hotspots']);
        }

        $payload = [
            'kind'     => $source->kind(),
            'pages'    => $urls,
            'options'  => $options,
            'lightbox' => (bool) $config->get('lightbox'),
            // Hotspots are invisible until a reader hovers one, which is right for
            // a magazine and wrong for a catalogue that is meant to be shoppable.
            'showHotspots' => (bool) $config->get('hotspotsShown'),
            // What a reader does is always reported to the page as an event; this
            // says whether the page should also hand it to an analytics service.
            'analytics'    => (string) $config->get('analytics'),
            // Every word the viewer says, in the site's language. Without this a
            // French site has English tooltips and a screen reader hears English.
            'strings'  => Strings::viewer($this->platform),
        ];

        // A book that opens over the page shows only its cover until it is asked
        // for, so the cover is worth making on the server: otherwise every reader
        // downloads the whole document to draw one thumbnail they may never use.
        if ($payload['lightbox']) {
            $cover = Cover::url($this->platform, $source);

            if ($cover !== '') {
                $payload['cover'] = $cover;
            }
        }

        if ($config->get('download') && $source->kind() !== Source::KIND_IMAGES) {
            $payload['options']['downloadUrl'] = $urls[0];
        }

        if ($config->get('sound')) {
            // Whatever the site has in its sounds folder: one chosen recording, or
            // all of them for the viewer to pick between.
            $sounds = Sounds::urls($this->platform, (string) $config->get('soundFile'));

            if ($sounds !== []) {
                $payload['options']['soundUrl'] = $sounds;
            }
        }

        $json = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP);

        return sprintf(
            '<div class="hikari-flipbook" id="%s" style="%s" data-flipbook=\'%s\'>%s</div>',
            $this->platform->escape($id),
            $this->platform->escape($this->style($config)),
            $json === false ? '{}' : $json,
            Seo::markup($this->platform, $source, $urls, $config)
        );
    }

    /**
     * Which URL parameter a book tracks its page in.
     *
     * The first book on the page keeps the plain name, so an ordinary page with
     * one book has an ordinary link. Every book after it gets its own, because
     * the URL is shared and two books writing the same parameter would overwrite
     * each other on every turn.
     *
     * @param  mixed $value What the settings asked for.
     * @return mixed
     */
    public static function deepLinkName($value, int $instance)
    {
        return $value === true && $instance > 1 ? 'page' . $instance : $value;
    }

    /**
     * A book sold as a product: shown to whoever bought it, and to nobody else.
     *
     * The check is here rather than in the browser because a book hidden by
     * JavaScript is not hidden: the document's URL is in the payload, and anyone
     * reading the page source has it. A locked book is never rendered at all, so
     * the URL is never written, no asset is enqueued and no cover is made.
     *
     * A site with no shop cannot tell a buyer from anyone else, so it shows the
     * book to nobody: a setting that cannot be honoured must not be ignored.
     *
     * @return string|null Null when the book may be shown, otherwise what goes in
     *                     its place.
     */
    private function locked(Config $config): ?string
    {
        $wanted = array_filter(array_map('trim', explode(',', (string) $config->get('bought'))), 'strlen');

        if ($wanted === []) {
            return null;
        }

        if (!$this->platform instanceof Shop) {
            return '';
        }

        // Several products means any of them: an edition sold on its own and the
        // bundle it is also part of both let a reader in.
        foreach ($wanted as $id) {
            if ($this->platform->hasBought($id)) {
                return null;
            }
        }

        return $this->buyersOnly(reset($wanted));
    }

    /** Says why the book is not there, and where the reader can buy it. */
    private function buyersOnly(string $id): string
    {
        $words   = Strings::server($this->platform);
        $product = $this->platform instanceof Shop ? $this->platform->product($id) : null;

        if ($product === null || $product['name'] === '') {
            return '<p class="hikari-flipbook-buyers">'
                . $this->platform->escape($words['buyersOnlyPlain']) . '</p>';
        }

        // The message is escaped first and the link put in after, so a translated
        // string is text and only the link we built is markup.
        $link = '<a href="' . $this->platform->escape($product['url']) . '">'
            . $this->platform->escape($product['name']) . '</a>';

        return '<p class="hikari-flipbook-buyers">'
            . str_replace('{product}', $link, $this->platform->escape($words['buyersOnly']))
            . '</p>';
    }

    /**
     * Turns "this region is product 42" into a link to product 42.
     *
     * The resolving happens here rather than in the browser so that a hotspot is
     * an ordinary link in the page: it works without the shop knowing anything
     * about flipbooks, it can be opened in a new tab, and a reader using a screen
     * reader hears the product's name rather than "button".
     *
     * @param  array<int,array<string,mixed>> $hotspots
     * @return array<int,array<string,mixed>>
     */
    private function shoppable(array $hotspots): array
    {
        if (!$this->platform instanceof Shop) {
            return $hotspots;
        }

        foreach ($hotspots as &$spot) {
            $id = (string) ($spot['data']['product'] ?? '');

            // A link the site typed itself wins: it said where it wanted to go.
            if ($id === '' || isset($spot['href'])) {
                continue;
            }

            $product = $this->platform->product($id);

            if ($product === null) {
                continue;
            }

            $spot['href'] = $product['url'];

            if (!isset($spot['label']) && $product['name'] !== '') {
                $spot['label'] = $product['name'];
            }
        }

        return $hotspots;
    }

    /** Colour choices ride on the container as custom properties, not as rules. */
    private function style(Config $config): string
    {
        $out = '';

        foreach ($config->toStyle() as $property => $value) {
            $out .= $property . ':' . $value . ';';
        }

        return $out;
    }
}
