<?php
/**
 * @package     Hikari.Flipbook
 * @copyright   Copyright (C) 2026 Hikari Software. All rights reserved.
 * @license     GNU General Public License version 3 or later
 */

namespace Hikari\Component\Hikariflipbook\Administrator\Field;

use Hikari\Flipbook\Core\Paths;
use Hikari\Flipbook\Core\Source;
use Hikari\Flipbook\Core\SourceException;
use Hikari\Flipbook\Platform\JoomlaPlatform;
use Joomla\CMS\Factory;
use Joomla\CMS\Form\FormField;
use Joomla\CMS\Language\Text;
use Joomla\Registry\Registry;

/**
 * The regions drawn on a book's pages.
 *
 * The field itself is a hidden input holding JSON. Everything a person sees is
 * built by the editor script around it, which is shared with WordPress: this
 * class exists to hand it the book's pages and the site's own words.
 */
class HotspotsField extends FormField
{
    protected $type = 'Hotspots';

    protected function getInput()
    {
        $path = (string) $this->form->getValue('path');

        // A book with no document yet: the editor has nothing to draw on, and
        // saying so beats an empty box.
        if ($path === '') {
            return '<p class="text-muted">' . Text::_('COM_HIKARIFLIPBOOK_HOTSPOTS_NO_BOOK') . '</p>';
        }

        // The component has its own copy of the core, loaded on demand: no other
        // screen in here needs it.
        require_once JPATH_ADMINISTRATOR . '/components/com_hikariflipbook/lib/bootstrap.php';

        $platform = new JoomlaPlatform(new Registry());

        try {
            $source = Source::fromPath($platform, $path);
        } catch (SourceException $e) {
            return '<p class="text-muted">' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . '</p>';
        }

        $assets = Factory::getApplication()->getDocument()->getWebAssetManager();
        $assets->registerAndUseStyle('hikariflipbook.hotspots', 'media/hikariflipbook/css/hotspot-editor.css');
        $assets->registerAndUseScript(
            'hikariflipbook.hotspots',
            'media/hikariflipbook/js/hotspot-editor.js',
            [],
            ['type' => 'module']
        );

        $payload = json_encode(
            [
                'kind'    => $source->kind(),
                'pages'   => Paths::urls($platform, $source),
                'strings' => self::strings(),
            ],
            JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP
        );

        return sprintf(
            '<div class="hikari-hotspots" data-hotspot-editor=\'%s\'>'
            . '<input type="hidden" name="%s" id="%s" value="%s"></div>',
            $payload === false ? '{}' : $payload,
            htmlspecialchars($this->name, ENT_QUOTES, 'UTF-8'),
            htmlspecialchars($this->id, ENT_QUOTES, 'UTF-8'),
            htmlspecialchars(self::value($this->value), ENT_QUOTES, 'UTF-8')
        );
    }

    /** Stored JSON, or a list the model already decoded. */
    private static function value($value): string
    {
        if (is_array($value)) {
            $json = json_encode(array_values($value), JSON_UNESCAPED_SLASHES);

            return $json === false ? '[]' : $json;
        }

        return trim((string) $value) === '' ? '[]' : (string) $value;
    }

    /** @return array<string,string> */
    private static function strings(): array
    {
        $out = [];

        foreach (['add', 'help', 'none', 'region', 'remove', 'href', 'tab', 'jump', 'product',
            'prev', 'next', 'x', 'y', 'width', 'height', 'unreadable'] as $key) {
            $out[$key] = Text::_('COM_HIKARIFLIPBOOK_HOTSPOTS_' . strtoupper($key));
        }

        // The region's own name, which cannot share a key with the field's label.
        $out['label'] = Text::_('COM_HIKARIFLIPBOOK_HOTSPOTS_LABEL_FIELD');

        return $out;
    }
}
