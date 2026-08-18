<?php
/**
 * @package     Hikari.Flipbook
 * @copyright   Copyright (C) 2026 Hikari Software. All rights reserved.
 * @license     GNU General Public License version 3 or later
 */

namespace Hikari\Flipbook\Core;

/**
 * A book a site has defined once and can place anywhere.
 *
 * Where it is stored is the host's business: Joomla keeps a table, WordPress keeps
 * a post type. What a book *is* has to be the same on both, so it is described
 * here and nowhere else.
 */
final class Book
{
    /** @var string */
    private $title;

    /** @var string */
    private $path;

    /** @var array<string,mixed> */
    private $options;

    /** @var string The host's own notion of who may see it: a view level, a capability. */
    private $access;

    /**
     * @param array<string,mixed> $options
     */
    public function __construct(string $title, string $path, array $options = [], string $access = '')
    {
        $this->title   = $title;
        $this->path    = $path;
        $this->options = $options;
        $this->access  = $access;
    }

    /**
     * Reads a stored row. Options may arrive as JSON, since that is how both hosts
     * end up keeping them.
     *
     * @param array<string,mixed> $row
     */
    public static function fromRow(array $row): self
    {
        $options = $row['options'] ?? $row['params'] ?? [];

        if (is_string($options)) {
            $decoded = json_decode($options, true);
            $options = is_array($decoded) ? $decoded : [];
        }

        return new self(
            (string) ($row['title'] ?? ''),
            (string) ($row['path'] ?? ''),
            is_array($options) ? $options : [],
            (string) ($row['access'] ?? '')
        );
    }

    public function title(): string
    {
        return $this->title;
    }

    public function path(): string
    {
        return $this->path;
    }

    public function access(): string
    {
        return $this->access;
    }

    /** @return array<string,mixed> */
    public function options(): array
    {
        return $this->options;
    }

    /**
     * The book's own settings, with anything the caller gave taking precedence: a
     * shortcode saying mode="single" means this book, this time.
     *
     * @param  array<string,mixed> $overrides
     * @return array<string,mixed>
     */
    public function merged(array $overrides = []): array
    {
        $out = array_merge($this->options, ['path' => $this->path]);

        foreach ($overrides as $key => $value) {
            if ($value === '' || $value === null) {
                continue;
            }
            $out[$key] = $value;
        }

        return $out;
    }
}
