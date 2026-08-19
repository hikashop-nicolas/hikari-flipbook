<?php
/**
 * @package     Hikari.Flipbook
 * @copyright   Copyright (C) 2026 Hikari Software. All rights reserved.
 * @license     GNU General Public License version 3 or later
 */

namespace Hikari\Flipbook\Core;

use Hikari\Flipbook\Platform\Platform;

/**
 * Resolves what a book is made of: one PDF, or a folder of images.
 *
 * Paths come from site configuration, so they are treated as untrusted: a book
 * may only point inside the host's public root, and only at extensions we serve.
 */
final class Source
{
    public const KIND_PDF    = 'pdf';
    public const KIND_IMAGES = 'images';
    public const KIND_EPUB   = 'epub';
    public const KIND_HTML   = 'html';
    /** A comic archive: a zip of pictures, one per page. */
    public const KIND_CBZ    = 'cbz';

    /** One file is one of these, and the extension says which. */
    private const FILE_KINDS = [
        'pdf'  => self::KIND_PDF,
        'epub' => self::KIND_EPUB,
        'cbz'  => self::KIND_CBZ,
    ];

    private const IMAGE_TYPES = ['jpg', 'jpeg', 'png', 'webp', 'gif', 'avif'];

    /** A folder of pages a browser can show as they are. */
    private const PAGE_TYPES = ['html', 'xhtml', 'htm'];

    /** @var string */
    private $kind;

    /** @var string[] */
    private $files;

    /**
     * Where a browser is to fetch these files, when it cannot work it out from
     * where they are. Empty for an ordinary book, which is a file under the site.
     *
     * @var string[]
     */
    private $urls = [];

    private function __construct(string $kind, array $files, array $urls = [])
    {
        $this->kind  = $kind;
        $this->files = $files;
        $this->urls  = $urls;
    }

    /** @throws SourceException when the path escapes the root or holds nothing usable */
    public static function fromPath(Platform $platform, string $path): self
    {
        $real = self::resolve($platform, $path);

        if (is_file($real)) {
            $kind = self::FILE_KINDS[strtolower(pathinfo($real, PATHINFO_EXTENSION))] ?? null;

            if ($kind === null) {
                throw new SourceException('A single file source must be a PDF, an EPUB or a CBZ.');
            }

            return new self($kind, [$real]);
        }

        $images = [];
        $pages  = [];

        foreach (scandir($real) ?: [] as $entry) {
            $file = $real . '/' . $entry;

            if (!is_file($file)) {
                continue;
            }

            $type = strtolower(pathinfo($file, PATHINFO_EXTENSION));

            if (in_array($type, self::IMAGE_TYPES, true)) {
                $images[] = $file;
            } elseif (in_array($type, self::PAGE_TYPES, true)) {
                $pages[] = $file;
            }
        }

        // Pictures first, because a folder of pages usually has a picture or two in
        // it and a folder of pictures never has pages.
        if ($images !== []) {
            natcasesort($images);

            return new self(self::KIND_IMAGES, array_values($images));
        }

        if ($pages !== []) {
            natcasesort($pages);

            return new self(self::KIND_HTML, array_values($pages));
        }

        throw new SourceException('That folder holds no pages: no images, and no HTML files.');
    }

    /**
     * A document the shop holds, which is not a path a site typed and need not be
     * anywhere a browser can reach: it is served by the host, at the address given
     * here, to whoever bought it.
     *
     * @param  string[] $urls One per file, in the same order.
     * @throws SourceException when it is not a document we can show
     */
    public static function fromShopFile(string $path, array $urls): self
    {
        $kind = self::FILE_KINDS[strtolower(pathinfo($path, PATHINFO_EXTENSION))] ?? null;

        if ($kind === null || !is_file($path)) {
            throw new SourceException('That product\'s file is not a PDF or an EPUB.');
        }

        return new self($kind, [Paths::normalise($path)], $urls);
    }

    public function kind(): string
    {
        return $this->kind;
    }

    /**
     * The addresses the browser is given, when they are not the files' own.
     *
     * @return string[]
     */
    public function urls(): array
    {
        return $this->urls;
    }

    /** @return string[] absolute paths */
    public function files(): array
    {
        return $this->files;
    }

    /**
     * A configured path must stay inside the public root. Anything that resolves
     * outside it is refused rather than clamped: a book pointing at /etc is a
     * mistake worth surfacing, not worth guessing about.
     */
    private static function resolve(Platform $platform, string $path): string
    {
        $rawRoot = rtrim(Paths::normalise($platform->rootPath()), '/');
        $root = Paths::root($platform);
        $candidate = Paths::normalise(trim($path));

        if ($candidate === '') {
            throw new SourceException('No path was given.');
        }

        $absolute = self::isAbsolute($candidate) ? $candidate : $rawRoot . '/' . ltrim($candidate, '/');
        $real = realpath($absolute);

        if ($real === false) {
            throw new SourceException('That path does not exist.');
        }

        $real = Paths::normalise($real);

        if (!Paths::isInside($real, $root)) {
            throw new SourceException('That path is outside the site.');
        }

        return $real;
    }

    private static function isAbsolute(string $path): bool
    {
        return $path[0] === '/' || preg_match('#^[A-Za-z]:/#', $path) === 1;
    }
}
