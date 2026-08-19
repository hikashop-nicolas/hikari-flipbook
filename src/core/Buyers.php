<?php
/**
 * @package     Hikari.Flipbook
 * @copyright   Copyright (C) 2026 Hikari Software. All rights reserved.
 * @license     GNU General Public License version 3 or later
 */

namespace Hikari\Flipbook\Core;

use Hikari\Flipbook\Platform\Platform;

/**
 * A book that is sold: who may read it, and what they read.
 *
 * Everything about the "bought" setting lives here, so that the rule is written
 * once and asked for from three places: the placement that shows the book, the
 * renderer, and the address that streams the document to a buyer.
 */
final class Buyers
{
    /**
     * The products named by a placement, in the order they were named.
     *
     * @return array<int,string>
     */
    public static function wanted(Config $config): array
    {
        return array_values(array_filter(
            array_map('trim', explode(',', (string) $config->get('bought'))),
            'strlen'
        ));
    }

    /**
     * Whether this visitor may read a book sold as one of these products.
     *
     * A host with no shop cannot tell a buyer from anyone else, so it lets nobody
     * in: a rule that cannot be applied must not be ignored.
     */
    public static function allowed(Platform $platform, array $wanted): bool
    {
        if ($wanted === []) {
            return true;
        }

        if (!$platform instanceof Shop) {
            return false;
        }

        // Several products means any of them: an edition sold on its own and the
        // bundle it is also part of both let a reader in.
        foreach ($wanted as $id) {
            if ($platform->hasBought($id)) {
                return true;
            }
        }

        return false;
    }

    /**
     * What goes in the book's place, or null when the book itself may be shown.
     */
    public static function locked(Platform $platform, Config $config): ?string
    {
        $wanted = self::wanted($config);

        if (self::allowed($platform, $wanted)) {
            return null;
        }

        return self::message($platform, $wanted[0]);
    }

    /**
     * The book a product is sold with, for a placement that named no document of
     * its own: a publisher who has already uploaded the PDF to the product should
     * not have to put the same file somewhere else as well.
     *
     * @return Source|null Null when this visitor may not have it, which is the
     *                     renderer's business rather than an error.
     * @throws SourceException when they may, and there is nothing to show them.
     */
    public static function document(Platform $platform, Config $config): ?Source
    {
        $wanted = self::wanted($config);

        if ($wanted === [] || !$platform instanceof Shop) {
            throw new SourceException('No path was given.');
        }

        if (!self::allowed($platform, $wanted)) {
            return null;
        }

        foreach ($wanted as $id) {
            $document = $platform->productDocument($id);

            if ($document !== null) {
                return Source::fromShopFile($document['path'], [$document['url']]);
            }
        }

        throw new SourceException(
            'That product has no PDF or EPUB the shop can show, so there is nothing to place.'
        );
    }

    /**
     * Streams a product's document to a buyer, for the address each host answers
     * on. The purchase is checked here and not only where the book was placed: an
     * address that is guessed at is still an address.
     *
     * @return bool False when this visitor may not have it, so the host can answer 404.
     */
    public static function send(Platform $platform, string $id): bool
    {
        if (!$platform instanceof Shop || $id === '' || !$platform->hasBought($id)) {
            return false;
        }

        $document = $platform->productDocument($id);

        if ($document === null || !is_file($document['path'])) {
            return false;
        }

        Download::send($document['path']);

        return true;
    }

    /** Says why the book is not there, and where the reader can buy it. */
    private static function message(Platform $platform, string $id): string
    {
        $words   = Strings::server($platform);
        $product = $platform instanceof Shop ? $platform->product($id) : null;

        if ($product === null || $product['name'] === '') {
            return '<p class="hikari-flipbook-buyers">'
                . $platform->escape($words['buyersOnlyPlain']) . '</p>';
        }

        // The message is escaped first and the link put in after, so a translated
        // string is text and only the link we built is markup.
        $link = '<a href="' . $platform->escape($product['url']) . '">'
            . $platform->escape($product['name']) . '</a>';

        return '<p class="hikari-flipbook-buyers">'
            . str_replace('{product}', $link, $platform->escape($words['buyersOnly']))
            . '</p>';
    }
}
