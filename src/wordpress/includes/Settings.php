<?php
/**
 * @package     Hikari.Flipbook
 * @copyright   Copyright (C) 2026 Hikari Software. All rights reserved.
 * @license     GNU General Public License version 3 or later
 */

namespace Hikari\Flipbook\WordPress;

use Hikari\Flipbook\Core\Sounds;
use Hikari\Flipbook\Platform\WordPressPlatform;

/**
 * The site-wide defaults, and the screen that edits them.
 *
 * A shortcode or a block says what is different about one book; this says what is
 * true of all of them, so a site is not repeating the same six attributes.
 */
final class Settings
{
    public const OPTION = 'hikari_flipbook_settings';

    /** @var array<string,mixed> */
    private const DEFAULTS = [
        'mode'       => 'auto',
        'showCover'  => 1,
        'zoom'       => 1,
        'sound'      => 0,
        'soundFile'  => '',
        'deepLink'   => 0,
        'download'   => 0,
        'share'      => 0,
        'lightbox'   => 0,
        'maxHeight'  => 0,
        'barColour'  => '',
        'pageColour' => '',
    ];

    /** @return array<string,mixed> */
    public static function all(): array
    {
        $saved = get_option(self::OPTION, []);

        return array_merge(self::DEFAULTS, is_array($saved) ? $saved : []);
    }

    public static function register(): void
    {
        add_action('admin_menu', [self::class, 'menu']);
        add_action('admin_init', [self::class, 'fields']);
    }

    public static function menu(): void
    {
        add_options_page(
            __('Hikari Flipbook', 'hikari-flipbook'),
            __('Hikari Flipbook', 'hikari-flipbook'),
            'manage_options',
            'hikari-flipbook',
            [self::class, 'screen']
        );
    }

    public static function fields(): void
    {
        register_setting('hikari_flipbook', self::OPTION, [
            'type'              => 'array',
            'sanitize_callback' => [self::class, 'sanitise'],
            'default'           => self::DEFAULTS,
        ]);
    }

    /**
     * @param  mixed $input
     * @return array<string,mixed>
     */
    public static function sanitise($input): array
    {
        $input = is_array($input) ? $input : [];
        $out   = [];

        foreach (self::DEFAULTS as $key => $default) {
            $value = $input[$key] ?? $default;

            if (is_int($default)) {
                $out[$key] = (int) $value;
                continue;
            }
            // Colours end up in a style attribute, so only a hex value is kept.
            if ($key === 'barColour' || $key === 'pageColour') {
                $out[$key] = preg_match('/^#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', (string) $value) === 1
                    ? (string) $value
                    : '';
                continue;
            }
            $out[$key] = sanitize_text_field((string) $value);
        }

        return $out;
    }

    public static function screen(): void
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        $values = self::all();
        $sounds = Sounds::available(new WordPressPlatform([]));

        echo '<div class="wrap"><h1>' . esc_html__('Hikari Flipbook', 'hikari-flipbook') . '</h1>';
        echo '<p>' . esc_html__(
            'These are the defaults. A shortcode or a block can override any of them.',
            'hikari-flipbook'
        ) . '</p>';
        echo '<form method="post" action="options.php">';
        settings_fields('hikari_flipbook');
        echo '<table class="form-table" role="presentation">';

        self::choice(__('Pages shown', 'hikari-flipbook'), 'mode', $values['mode'], [
            'auto'   => __('One or two, depending on the screen', 'hikari-flipbook'),
            'single' => __('One page', 'hikari-flipbook'),
            'double' => __('Two pages', 'hikari-flipbook'),
        ]);
        self::toggle(__('First page stands alone as a cover', 'hikari-flipbook'), 'showCover', $values['showCover']);
        self::toggle(__('Allow zooming', 'hikari-flipbook'), 'zoom', $values['zoom']);
        self::toggle(__('Page turn sound', 'hikari-flipbook'), 'sound', $values['sound']);

        self::choice(
            __('Which page turn sound', 'hikari-flipbook'),
            'soundFile',
            $values['soundFile'],
            ['' => __('A different one each turn', 'hikari-flipbook')] + array_combine($sounds, $sounds),
            __('Every audio file in the plugin\'s media/sounds folder is offered here.', 'hikari-flipbook')
        );

        self::toggle(__('Keep the page number in the address bar', 'hikari-flipbook'), 'deepLink', $values['deepLink']);
        self::toggle(__('Offer the PDF for download', 'hikari-flipbook'), 'download', $values['download']);
        self::toggle(__('Show a share button', 'hikari-flipbook'), 'share', $values['share']);
        self::toggle(__('Show the cover, open the book over the page', 'hikari-flipbook'), 'lightbox', $values['lightbox']);
        self::number(__('Largest height (px)', 'hikari-flipbook'), 'maxHeight', $values['maxHeight'],
            __('Zero lets the book use the height of the screen.', 'hikari-flipbook'));
        self::colour(__('Toolbar colour', 'hikari-flipbook'), 'barColour', $values['barColour']);
        self::colour(__('Page colour', 'hikari-flipbook'), 'pageColour', $values['pageColour']);

        echo '</table>';
        submit_button();
        echo '</form></div>';
    }

    private static function row(string $label, string $field, string $note = ''): void
    {
        echo '<tr><th scope="row">' . esc_html($label) . '</th><td>' . $field;
        if ($note !== '') {
            echo '<p class="description">' . esc_html($note) . '</p>';
        }
        echo '</td></tr>';
    }

    private static function name(string $key): string
    {
        return esc_attr(self::OPTION . '[' . $key . ']');
    }

    private static function toggle(string $label, string $key, $value): void
    {
        self::row($label, sprintf(
            '<label><input type="checkbox" name="%s" value="1" %s> %s</label>',
            self::name($key),
            checked((int) $value, 1, false),
            esc_html__('Yes', 'hikari-flipbook')
        ));
    }

    private static function number(string $label, string $key, $value, string $note = ''): void
    {
        self::row($label, sprintf(
            '<input type="number" min="0" name="%s" value="%s" class="small-text">',
            self::name($key),
            esc_attr((string) $value)
        ), $note);
    }

    private static function colour(string $label, string $key, $value): void
    {
        self::row($label, sprintf(
            '<input type="text" name="%s" value="%s" placeholder="#1976d2" class="regular-text">',
            self::name($key),
            esc_attr((string) $value)
        ));
    }

    /** @param array<string,string> $options */
    private static function choice(string $label, string $key, $value, array $options, string $note = ''): void
    {
        $html = '<select name="' . self::name($key) . '">';
        foreach ($options as $option => $text) {
            $html .= sprintf(
                '<option value="%s" %s>%s</option>',
                esc_attr((string) $option),
                selected((string) $value, (string) $option, false),
                esc_html($text)
            );
        }
        self::row($label, $html . '</select>', $note);
    }
}
