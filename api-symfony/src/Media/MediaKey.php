<?php

declare(strict_types=1);

namespace App\Media;

final class MediaKey
{
    public static function assertValid(string $key): void
    {
        if ('' === $key || \strlen($key) > 255 || !preg_match('#^[A-Za-z0-9][A-Za-z0-9._/-]*$#', $key)
            || str_contains($key, '..') || str_contains($key, '//') || str_ends_with($key, '/')) {
            throw new \InvalidArgumentException(\sprintf('Invalid media key "%s".', $key));
        }
    }

    /** Never throws: legacy file names may contain spaces or non-ASCII characters, so each segment is percent-encoded. */
    public static function url(string $baseUrl, string $key): string
    {
        return rtrim($baseUrl, '/').'/'.implode('/', array_map('rawurlencode', explode('/', $key)));
    }
}
