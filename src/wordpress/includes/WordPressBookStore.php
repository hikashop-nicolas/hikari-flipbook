<?php
/**
 * @package     Hikari.Flipbook
 * @copyright   Copyright (C) 2026 Hikari Software. All rights reserved.
 * @license     GNU General Public License version 3 or later
 */

namespace Hikari\Flipbook\Platform;

use Hikari\Flipbook\Core\Book;
use Hikari\Flipbook\Core\BookStore;
use Hikari\Flipbook\WordPress\BookType;

/** Books kept as posts of the hikari_book type. */
final class WordPressBookStore implements BookStore
{
    public function find($id): ?Book
    {
        $id = (int) $id;

        if ($id <= 0) {
            return null;
        }

        $post = get_post($id);

        if (!$post || $post->post_type !== BookType::TYPE) {
            return null;
        }

        // Published is public; anything else needs someone who may read it, which
        // is how a draft or a private book stays out of a visitor's page.
        if ($post->post_status !== 'publish' && !current_user_can('read_post', $id)) {
            return null;
        }

        $meta = get_post_meta($id, BookType::META, true);
        $meta = is_array($meta) ? $meta : [];

        return Book::fromRow([
            'title'   => $post->post_title,
            'path'    => (string) ($meta['path'] ?? ''),
            'options' => $meta,
            'access'  => $post->post_status,
        ]);
    }
}
