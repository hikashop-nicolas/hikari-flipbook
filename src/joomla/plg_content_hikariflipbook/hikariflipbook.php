<?php
/**
 * @package     Hikari.Flipbook
 * @copyright   Copyright (C) 2026 Hikari Software. All rights reserved.
 * @license     GNU General Public License version 3 or later
 */

use Hikari\Flipbook\Core\Buyers;
use Hikari\Flipbook\Core\Config;
use Hikari\Flipbook\Core\Renderer;
use Hikari\Flipbook\Core\Source;
use Hikari\Flipbook\Core\SourceException;
use Hikari\Flipbook\Core\Book;
use Hikari\Flipbook\Platform\JoomlaBookStore;
use Hikari\Flipbook\Platform\JoomlaPlatform;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\Registry\Registry;

require_once __DIR__ . '/lib/bootstrap.php';

/**
 * Turns {flipbook path="images/catalogue.pdf"} in an article into a book.
 *
 * Every module setting works here as an attribute, so the two ways of placing a
 * book behave the same: the shortcode's attributes are merged over the plugin's
 * own defaults and handed to the same core.
 */
class PlgContentHikariflipbook extends CMSPlugin
{
    protected $autoloadLanguage = true;

    /** @var int Books rendered so far, for unique element ids. */
    private $count = 0;

    public function onContentPrepare($context, &$article, &$params, $page = 0): void
    {
        if ($context === 'com_finder.indexer' || !isset($article->text)) {
            return;
        }
        if (stripos($article->text, '{flipbook') === false) {
            return;
        }

        $article->text = preg_replace_callback(
            '/\{flipbook\s*([^}]*)\}/i',
            function (array $match): string {
                return $this->book($this->attributes($match[1]));
            },
            $article->text
        );
    }

    /** @param array<string,string> $atts */
    private function book(array $atts): string
    {
        $settings = array_merge($this->params->toArray(), $atts);

        // {flipbook book="3"} places a saved book; the attributes still win, so an
        // article can borrow a book and show it its own way.
        $wanted = (int) ($settings['book'] ?? 0);
        $book   = (new JoomlaBookStore())->find($wanted);

        if ($book instanceof Book) {
            $settings = $book->merged($settings);
        } elseif ($wanted > 0) {
            // Unpublished, or not for this language or this visitor. An article
            // that silently loses its book helps nobody.
            return $this->complain(Text::_('PLG_CONTENT_HIKARIFLIPBOOK_BOOK_UNAVAILABLE'));
        }

        $params   = new Registry($settings);
        $platform = new JoomlaPlatform($params, 'hikariflipbook');

        $config = new Config($settings);
        $path   = (string) ($settings['path'] ?? '');

        try {
            // A book sold as a product needs no path of its own: the file the
            // product is sold with is the book.
            $source = $path === '' && $config->get('bought') !== ''
                ? Buyers::document($platform, $config)
                : Source::fromPath($platform, $path);
        } catch (SourceException $e) {
            return $this->complain($e->getMessage());
        }

        $this->count++;

        return (new Renderer($platform))->render(
            $source,
            $config,
            'hikari-flipbook-content-' . $this->count
        );
    }

    /** Only someone who could fix the article is told what is wrong with it. */
    private function complain(string $message): string
    {
        $user = Factory::getApplication()->getIdentity();

        if ($user === null || !$user->authorise('core.edit', 'com_content')) {
            return '';
        }

        return '<div class="hikari-flipbook-error">'
            . Text::sprintf('PLG_CONTENT_HIKARIFLIPBOOK_ERROR', htmlspecialchars($message, ENT_QUOTES, 'UTF-8'))
            . '</div>';
    }

    /**
     * Reads path="x" mode="single" out of a shortcode.
     *
     * Deliberately narrow: only known settings are read, so a typo in an article
     * cannot reach the core as an option, and quotes are required so a bare value
     * cannot swallow the rest of the tag.
     *
     * @return array<string,string>
     */
    private function attributes(string $raw): array
    {
        $out = [];

        if (preg_match_all('/(\w+)\s*=\s*"([^"]*)"/', $raw, $matches, PREG_SET_ORDER) === false) {
            return $out;
        }

        foreach ($matches as $match) {
            $out[$match[1]] = $match[2];
        }

        return $out;
    }
}
