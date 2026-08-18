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
    /** @var Platform */
    private $platform;

    public function __construct(Platform $platform)
    {
        $this->platform = $platform;
    }

    public function render(Source $source, Config $config, string $id): string
    {
        $this->platform->enqueue('hikari-flipbook', 'js/flipbook.js', 'script');
        $this->platform->enqueue('hikari-flipbook', 'css/flipbook.css', 'style');

        $urls = Paths::urls($this->platform, $source);

        $options = $config->toViewer();

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
            // Every word the viewer says, in the site's language. Without this a
            // French site has English tooltips and a screen reader hears English.
            'strings'  => Strings::viewer($this->platform),
        ];

        if ($config->get('download') && $source->kind() === Source::KIND_PDF) {
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
            '<div class="hikari-flipbook" id="%s" style="%s" data-flipbook=\'%s\'></div>',
            $this->platform->escape($id),
            $this->platform->escape($this->style($config)),
            $json === false ? '{}' : $json
        );
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
