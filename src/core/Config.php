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
        'mode'         => 'auto',
        'breakpoint'   => 700,
        'flippingTime' => 700,
        'showCover'    => true,
        'rtl'          => false,
        'zoom'         => true,
        'deepLink'     => false,
        'sound'        => false,
        'download'     => false,
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

        $this->values = $values;
    }

    public function get(string $key)
    {
        return $this->values[$key] ?? null;
    }

    /** The option set handed to the viewer, ready for json_encode. */
    public function toViewer(): array
    {
        return [
            'mode'         => $this->values['mode'],
            'breakpoint'   => $this->values['breakpoint'],
            'flippingTime' => $this->values['flippingTime'],
            'showCover'    => (bool) $this->values['showCover'],
            'rtl'          => (bool) $this->values['rtl'],
            'zoom'         => (bool) $this->values['zoom'],
            'deepLink'     => (bool) $this->values['deepLink'],
        ];
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
