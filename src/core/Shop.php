<?php
/**
 * @package     Hikari.Flipbook
 * @copyright   Copyright (C) 2026 Hikari Software. All rights reserved.
 * @license     GNU General Public License version 3 or later
 */

namespace Hikari\Flipbook\Core;

/**
 * A host that has a shop behind it.
 *
 * This is what makes a catalogue shoppable: a hotspot says which product it is
 * over, and the host turns that into a link a reader can follow. It is separate
 * from Platform because a site without a shop is not a broken site, and a host
 * that cannot answer simply does not implement this.
 */
interface Shop
{
    /**
     * @param  string $id The shop's own product id, as a site stored it.
     * @return array{url:string,name:string}|null Null when there is no such product.
     */
    public function product(string $id): ?array;

    /**
     * Whether the visitor reading this page has bought that product.
     *
     * What counts as bought is the shop's business, not ours: an order that was
     * placed and never paid for is not a purchase, and only the shop knows which
     * of its statuses mean it was. A host that cannot tell says no.
     */
    public function hasBought(string $id): bool;

    /**
     * The document this product is sold with, when the shop holds one we can show.
     *
     * A publisher selling a book has already uploaded it to the product; asking
     * them to put a second copy somewhere the web can read would be asking them to
     * publish for free what they are selling.
     *
     * The path is on this server and need not be public. The URL is where this
     * host answers for it, checking the purchase again as it does.
     *
     * @return array{path:string,url:string}|null Null when there is no such file,
     *                                            or it is not one we can show.
     */
    public function productDocument(string $id): ?array;
}
