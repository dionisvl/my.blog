<?php

declare(strict_types=1);

namespace App\Media;

/**
 * Storage port for images. Public objects are served to readers, private ones (originals) are never exposed over HTTP.
 * Keys are relative paths such as "uploads/2026-11-13_10-00-00_ab12cd34ef56ab12.webp".
 */
interface MediaStorage
{
    public function put(Visibility $visibility, string $key, string $localPath, string $contentType): void;

    public function get(Visibility $visibility, string $key): ?string;

    public function exists(Visibility $visibility, string $key): bool;

    public function delete(Visibility $visibility, string $key): void;

    /** @return iterable<string> every key of the given area */
    public function keys(Visibility $visibility): iterable;

    /** Public URL of a public key (a path on the main host for local, MEDIA_BASE_URL based for any driver). */
    public function url(string $key): string;
}
