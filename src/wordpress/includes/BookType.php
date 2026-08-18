<?php
/**
 * @package     Hikari.Flipbook
 * @copyright   Copyright (C) 2026 Hikari Software. All rights reserved.
 * @license     GNU General Public License version 3 or later
 */

namespace Hikari\Flipbook\WordPress;

use Hikari\Flipbook\Core\Hotspots;
use Hikari\Flipbook\Core\Paths;
use Hikari\Flipbook\Core\Source;
use Hikari\Flipbook\Core\SourceException;
use Hikari\Flipbook\Platform\WordPressPlatform;

/**
 * Books as a post type: WordPress already knows how to list, edit, restrict and
 * trash a post, so a book is one rather than a table of its own.
 */
final class BookType
{
    public const TYPE = 'hikari_book';
    public const META = '_hikari_flipbook';

    public static function register(): void
    {
        add_action('init', [self::class, 'declare']);
        add_action('add_meta_boxes', [self::class, 'box']);
        add_action('save_post_' . self::TYPE, [self::class, 'save'], 10, 2);
    }

    public static function declare(): void
    {
        register_post_type(self::TYPE, [
            'labels' => [
                'name'          => __('Flipbooks', 'hikari-flipbook'),
                'singular_name' => __('Flipbook', 'hikari-flipbook'),
                'add_new_item'  => __('Add a book', 'hikari-flipbook'),
                'edit_item'     => __('Edit book', 'hikari-flipbook'),
                'search_items'  => __('Search books', 'hikari-flipbook'),
                'not_found'     => __('No books yet.', 'hikari-flipbook'),
            ],
            'public'              => false,
            'show_ui'             => true,
            'show_in_menu'        => true,
            'menu_icon'           => 'dashicons-book-alt',
            'supports'            => ['title'],
            'capability_type'     => 'post',
            'exclude_from_search' => true,
            'has_archive'         => false,
            'rewrite'             => false,
        ]);
    }

    public static function box(): void
    {
        add_meta_box(
            'hikari-flipbook-book',
            __('The book', 'hikari-flipbook'),
            [self::class, 'render'],
            self::TYPE,
            'normal',
            'high'
        );

        add_meta_box(
            'hikari-flipbook-hotspots',
            __('Hotspots', 'hikari-flipbook'),
            [self::class, 'hotspots'],
            self::TYPE,
            'normal',
            'default'
        );
    }

    /**
     * The regions drawn on the pages. The editor is the same script Joomla's book
     * screen uses: this only has to hand it the pages and the site's own words.
     */
    public static function hotspots(\WP_Post $post): void
    {
        $saved  = get_post_meta($post->ID, self::META, true);
        $saved  = is_array($saved) ? $saved : [];
        $path   = (string) ($saved['path'] ?? '');
        $stored = Hotspots::encode(Hotspots::decode($saved['hotspots'] ?? []));

        self::toggleRow(
            __('Outline every region as soon as the book opens', 'hikari-flipbook'),
            'hotspotsShown',
            $saved['hotspotsShown'] ?? 0
        );

        if ($path === '') {
            echo '<p>' . esc_html__(
                'Save the book with a document first, then come back to draw on its pages.',
                'hikari-flipbook'
            ) . '</p>';
            return;
        }

        $platform = new WordPressPlatform([]);

        try {
            $source = Source::fromPath($platform, $path);
        } catch (SourceException $e) {
            echo '<p>' . esc_html($e->getMessage()) . '</p>';
            return;
        }

        $platform->enqueue('hikari-flipbook-editor', 'js/hotspot-editor.js', 'script');
        $platform->enqueue('hikari-flipbook-editor', 'css/hotspot-editor.css', 'style');

        $payload = wp_json_encode([
            'kind'    => $source->kind(),
            'pages'   => Paths::urls($platform, $source),
            'strings' => self::editorStrings(),
        ]);

        printf(
            '<div class="hikari-hotspots" data-hotspot-editor="%s">'
            . '<input type="hidden" name="hikari_flipbook_hotspots" value="%s"></div>',
            esc_attr((string) $payload),
            esc_attr($stored)
        );
    }

    /** @return array<string,string> */
    private static function editorStrings(): array
    {
        return [
            'add'        => __('Add a region', 'hikari-flipbook'),
            'help'       => __('Drag on the page to draw a region. Drag a region to move it, or its corner to resize it.', 'hikari-flipbook'),
            'none'       => __('No region selected.', 'hikari-flipbook'),
            'region'     => __('Region', 'hikari-flipbook'),
            'remove'     => __('Delete this region', 'hikari-flipbook'),
            'label'      => __('Name', 'hikari-flipbook'),
            'href'       => __('Link', 'hikari-flipbook'),
            'tab'        => __('Open in a new tab', 'hikari-flipbook'),
            'jump'       => __('Go to page', 'hikari-flipbook'),
            'product'    => __('Product id', 'hikari-flipbook'),
            'prev'       => __('Previous page', 'hikari-flipbook'),
            'next'       => __('Next page', 'hikari-flipbook'),
            'x'          => __('Left %', 'hikari-flipbook'),
            'y'          => __('Top %', 'hikari-flipbook'),
            'width'      => __('Width %', 'hikari-flipbook'),
            'height'     => __('Height %', 'hikari-flipbook'),
            'unreadable' => __('This book could not be opened.', 'hikari-flipbook'),
        ];
    }

    private static function toggleRow(string $label, string $key, $value): void
    {
        printf(
            '<p><label><input type="checkbox" name="hikari_flipbook[%s]" value="1" %s> %s</label></p>',
            esc_attr($key),
            checked((int) $value, 1, false),
            esc_html($label)
        );
    }

    public static function render(\WP_Post $post): void
    {
        $saved = get_post_meta($post->ID, self::META, true);
        $values = array_merge(['path' => ''], Settings::all(), is_array($saved) ? $saved : []);

        wp_nonce_field('hikari_flipbook_book', 'hikari_flipbook_nonce');

        echo '<p><label for="hikari-path"><strong>'
            . esc_html__('PDF or image folder', 'hikari-flipbook') . '</strong></label><br>';
        echo '<input type="text" id="hikari-path" name="hikari_flipbook[path]" class="large-text" value="'
            . esc_attr((string) $values['path']) . '"></p>';
        echo '<p class="description">' . esc_html__(
            'Relative to the site, for example wp-content/uploads/catalogue.pdf',
            'hikari-flipbook'
        ) . '</p>';

        echo '<p>' . esc_html__(
            'Anything left as the site default follows Settings, Hikari Flipbook. A shortcode or a block can still override either.',
            'hikari-flipbook'
        ) . '</p>';

        echo '<table class="form-table" role="presentation">';
        self::choice(__('Pages shown', 'hikari-flipbook'), 'mode', $values['mode'], [
            'auto'   => __('One or two, depending on the screen', 'hikari-flipbook'),
            'single' => __('One page', 'hikari-flipbook'),
            'double' => __('Two pages', 'hikari-flipbook'),
        ]);
        foreach ([
            'showCover' => __('First page stands alone as a cover', 'hikari-flipbook'),
            'zoom'      => __('Allow zooming', 'hikari-flipbook'),
            'sound'     => __('Page turn sound', 'hikari-flipbook'),
            'deepLink'  => __('Keep the page number in the address bar', 'hikari-flipbook'),
            'download'  => __('Offer the PDF for download', 'hikari-flipbook'),
            'share'     => __('Show a share button', 'hikari-flipbook'),
            'lightbox'  => __('Show the cover, open the book over the page', 'hikari-flipbook'),
        ] as $key => $label) {
            self::toggle($label, $key, $values[$key]);
        }
        self::text(__('Largest height (px)', 'hikari-flipbook'), 'maxHeight', $values['maxHeight']);
        self::text(__('Toolbar colour', 'hikari-flipbook'), 'barColour', $values['barColour']);
        self::text(__('Page colour', 'hikari-flipbook'), 'pageColour', $values['pageColour']);
        echo '</table>';

        echo '<p>' . esc_html__('Place it with:', 'hikari-flipbook') . ' <code>[hikari_flipbook book="'
            . (int) $post->ID . '"]</code></p>';
    }

    public static function save(int $id, \WP_Post $post): void
    {
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        if (!current_user_can('edit_post', $id)) {
            return;
        }
        $nonce = isset($_POST['hikari_flipbook_nonce']) ? sanitize_text_field(wp_unslash($_POST['hikari_flipbook_nonce'])) : '';
        if (!wp_verify_nonce($nonce, 'hikari_flipbook_book')) {
            return;
        }

        $input = isset($_POST['hikari_flipbook']) && is_array($_POST['hikari_flipbook'])
            ? wp_unslash($_POST['hikari_flipbook'])
            : [];

        // The settings go through the same sanitiser as the options screen, so a
        // book cannot hold a value the site itself could not.
        $clean         = Settings::sanitise($input);
        $clean['path'] = sanitize_text_field((string) ($input['path'] ?? ''));

        // Hotspots travel in their own field: they are JSON, and the settings
        // sanitiser would flatten them into a text line.
        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- Hotspots::decode is the sanitiser
        $raw                = isset($_POST['hikari_flipbook_hotspots']) ? wp_unslash($_POST['hikari_flipbook_hotspots']) : '';
        $clean['hotspots']  = Hotspots::encode(Hotspots::decode(is_string($raw) ? $raw : ''));

        update_post_meta($id, self::META, $clean);
    }

    private static function row(string $label, string $field): void
    {
        echo '<tr><th scope="row">' . esc_html($label) . '</th><td>' . $field . '</td></tr>';
    }

    private static function toggle(string $label, string $key, $value): void
    {
        self::row($label, sprintf(
            '<label><input type="checkbox" name="hikari_flipbook[%s]" value="1" %s> %s</label>',
            esc_attr($key),
            checked((int) $value, 1, false),
            esc_html__('Yes', 'hikari-flipbook')
        ));
    }

    private static function text(string $label, string $key, $value): void
    {
        self::row($label, sprintf(
            '<input type="text" name="hikari_flipbook[%s]" value="%s" class="regular-text">',
            esc_attr($key),
            esc_attr((string) $value)
        ));
    }

    /** @param array<string,string> $options */
    private static function choice(string $label, string $key, $value, array $options): void
    {
        $html = '<select name="hikari_flipbook[' . esc_attr($key) . ']">';
        foreach ($options as $option => $text) {
            $html .= sprintf(
                '<option value="%s" %s>%s</option>',
                esc_attr((string) $option),
                selected((string) $value, (string) $option, false),
                esc_html($text)
            );
        }
        self::row($label, $html . '</select>');
    }
}
