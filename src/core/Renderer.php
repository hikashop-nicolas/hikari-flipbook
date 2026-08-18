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

        $urls = $this->urls($source);

        $payload = [
            'kind'    => $source->kind(),
            'pages'   => $urls,
            'options' => $config->toViewer(),
            'lightbox' => (bool) $config->get('lightbox'),
        ];

        if ($config->get('download') && $source->kind() === Source::KIND_PDF) {
            $payload['options']['downloadUrl'] = $urls[0];
        }

        $json = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP);

        return sprintf(
            '<div class="hikari-flipbook" id="%s" style="%s" data-flipbook=\'%s\'></div>',
            $this->platform->escape($id),
            $this->platform->escape($this->style($config)),
            $json === false ? '{}' : $json
        );
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

    /** Absolute paths become public URLs; the viewer never sees a filesystem path. */
    private function urls(Source $source): array
    {
        $root = Paths::root($this->platform);
        $base = rtrim($this->platform->baseUrl(), '/');
        $urls = [];

        foreach ($source->files() as $file) {
            $file = Paths::normalise($file);
            $urls[] = Paths::isInside($file, $root) ? $base . substr($file, strlen($root)) : $file;
        }

        return $urls;
    }
}
