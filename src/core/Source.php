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

    /** One file is one of these, and the extension says which. */
    private const FILE_KINDS = ['pdf' => self::KIND_PDF, 'epub' => self::KIND_EPUB];

    private const IMAGE_TYPES = ['jpg', 'jpeg', 'png', 'webp', 'gif', 'avif'];

    /** @var string */
    private $kind;

    /** @var string[] */
    private $files;

    private function __construct(string $kind, array $files)
    {
        $this->kind  = $kind;
        $this->files = $files;
    }

    /** @throws SourceException when the path escapes the root or holds nothing usable */
    public static function fromPath(Platform $platform, string $path): self
    {
        $real = self::resolve($platform, $path);

        if (is_file($real)) {
            $kind = self::FILE_KINDS[strtolower(pathinfo($real, PATHINFO_EXTENSION))] ?? null;

            if ($kind === null) {
                throw new SourceException('A single file source must be a PDF or an EPUB.');
            }

            return new self($kind, [$real]);
        }

        $images = [];
        foreach (scandir($real) ?: [] as $entry) {
            $file = $real . '/' . $entry;
            if (!is_file($file)) {
                continue;
            }
            if (in_array(strtolower(pathinfo($file, PATHINFO_EXTENSION)), self::IMAGE_TYPES, true)) {
                $images[] = $file;
            }
        }

        if ($images === []) {
            throw new SourceException('That folder holds no images.');
        }

        natcasesort($images);

        return new self(self::KIND_IMAGES, array_values($images));
    }

    public function kind(): string
    {
        return $this->kind;
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
