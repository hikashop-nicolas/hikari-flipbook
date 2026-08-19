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
}
