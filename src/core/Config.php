<?php
/**
 * @package     Hikari.Flipbook
 * @copyright   Copyright (C) 2026 Hikari Software. All rights reserved.
 * @license     GNU General Public License version 3 or later
 */

namespace Hikari\Flipbook\Core;

/**
 * Turns whatever the host hands us into the option set flipview expects.
 *
 * Hosts disagree about types: Joomla parameters arrive as strings, WordPress
 * shortcode attributes as strings, block attributes as real types. Normalising
 * here means the viewer config is identical on both.
 */
final class Config
{
    private const DEFAULTS = [
        // The book itself
        'mode'         => 'auto',
        'breakpoint'   => 700,
        'flippingTime' => 700,
        'showCover'    => true,
        'hardCovers'   => false,
        'rtl'          => false,
        'maxHeight'    => 0,
        // What the reader can do
        'zoom'         => true,
        'sound'        => false,
        'soundFile'    => '',
        'deepLink'     => false,
        'download'     => false,
        'share'        => false,
        'lightbox'     => false,
        // Which buttons the toolbar shows
        'toolbar'      => true,
        'buttonNav'    => true,
        'buttonEnds'   => true,
        'buttonPage'   => true,
        // Appearance
        'barColour'    => '',
        'pageColour'   => '',
    ];

    private const MODES = ['auto', 'single', 'double'];

    /** @var array<string,mixed> */
    private $values;

    /** @param array<string,mixed> $input */
    public function __construct(array $input = [])
    {
        $values = self::DEFAULTS;

        foreach ($input as $key => $value) {
            if (!array_key_exists($key, $values)) {
                continue;
            }
            $values[$key] = self::coerce($values[$key], $value);
        }

        if (!in_array($values['mode'], self::MODES, true)) {
            $values['mode'] = self::DEFAULTS['mode'];
        }

        $values['breakpoint']   = max(240, min(2000, (int) $values['breakpoint']));
        $values['flippingTime'] = max(100, min(5000, (int) $values['flippingTime']));
        $values['maxHeight']    = max(0, min(4000, (int) $values['maxHeight']));
        $values['barColour']    = self::colour($values['barColour']);
        $values['pageColour']   = self::colour($values['pageColour']);

        $this->values = $values;
    }

    public function get(string $key)
    {
        return $this->values[$key] ?? null;
    }

    /** The option set handed to the viewer, ready for json_encode. */
    public function toViewer(): array
    {
        $options = [
            'mode'         => $this->values['mode'],
            'breakpoint'   => $this->values['breakpoint'],
            'flippingTime' => $this->values['flippingTime'],
            'showCover'    => (bool) $this->values['showCover'],
            'hardCovers'   => (bool) $this->values['hardCovers'],
            'rtl'          => (bool) $this->values['rtl'],
            'zoom'         => (bool) $this->values['zoom'],
            'deepLink'     => (bool) $this->values['deepLink'],
            'share'        => (bool) $this->values['share'],
        ];

        if ($this->values['maxHeight'] > 0) {
            $options['maxHeight'] = $this->values['maxHeight'];
        }

        // The viewer takes false for no toolbar, or an object of button switches.
        if (!$this->values['toolbar']) {
            $options['toolbar'] = false;
        } else {
            $options['toolbar'] = [
                'nav'       => (bool) $this->values['buttonNav'],
                'ends'      => (bool) $this->values['buttonEnds'],
                'pageInput' => (bool) $this->values['buttonPage'],
            ];
        }

        return $options;
    }

    /** The custom properties the container carries, so a site can restyle it. */
    public function toStyle(): array
    {
        $style = [];

        if ($this->values['barColour'] !== '') {
            $style['--fv-bar-bg'] = $this->values['barColour'];
        }
        if ($this->values['pageColour'] !== '') {
            $style['--fv-page-bg'] = $this->values['pageColour'];
        }

        return $style;
    }

    /** Only a hex colour is accepted: it ends up in a style attribute. */
    private static function colour($value): string
    {
        $value = trim((string) $value);

        return preg_match('/^#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', $value) === 1 ? $value : '';
    }

    /** Keep the declared type of the default: hosts send everything as strings. */
    private static function coerce($default, $value)
    {
        if (is_bool($default)) {
            return filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? $default;
        }
        if (is_int($default)) {
            return is_numeric($value) ? (int) $value : $default;
        }
        return is_scalar($value) ? (string) $value : $default;
    }
}
