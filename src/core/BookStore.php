<?php
/**
 * @package     Hikari.Flipbook
 * @copyright   Copyright (C) 2026 Hikari Software. All rights reserved.
 * @license     GNU General Public License version 3 or later
 */

namespace Hikari\Flipbook\Core;

/** Where a host keeps its books. One method: the core only ever reads one. */
interface BookStore
{
    /**
     * @param  int|string $id
     * @return Book|null  null when there is no such book, or it is unpublished
     */
    public function find($id): ?Book;
}
