<?php

declare(strict_types=1);

namespace App\Media;

final readonly class LocalMediaStorage implements MediaStorage
{
    public function __construct(
        private string $publicRoot,
        private string $privateRoot,
        private string $baseUrl,
    ) {
        $public = rtrim($publicRoot, '/').'/';
        $private = rtrim($privateRoot, '/').'/';

        if ($public === $private || str_starts_with($private, $public)) {
            throw new \InvalidArgumentException('The private root must not be inside the public root.');
        }
    }

    public function put(Visibility $visibility, string $key, string $localPath, string $contentType): void
    {
        $path = $this->path($visibility, $key);
        $dir = \dirname($path);

        if (!is_dir($dir) && !mkdir($dir, 0o755, true) && !is_dir($dir)) {
            throw new \RuntimeException(\sprintf('Cannot create directory "%s".', $dir));
        }

        $tmp = $path.'.'.bin2hex(random_bytes(4)).'.tmp';

        if (!copy($localPath, $tmp)) {
            throw new \RuntimeException('Cannot write media file.');
        }

        chmod($tmp, Visibility::Public === $visibility ? 0o644 : 0o640);

        if (!rename($tmp, $path)) {
            @unlink($tmp);

            throw new \RuntimeException('Cannot publish media file.');
        }
    }

    public function get(Visibility $visibility, string $key): ?string
    {
        $path = $this->path($visibility, $key);
        $data = is_file($path) ? file_get_contents($path) : false;

        return false === $data ? null : $data;
    }

    public function exists(Visibility $visibility, string $key): bool
    {
        return is_file($this->path($visibility, $key));
    }

    public function delete(Visibility $visibility, string $key): void
    {
        $path = $this->path($visibility, $key);

        if (is_file($path)) {
            unlink($path);
        }
    }

    public function keys(Visibility $visibility): iterable
    {
        $root = $this->root($visibility);

        if (!is_dir($root)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS));

        foreach ($iterator as $file) {
            if ($file->isFile() && !str_ends_with($file->getFilename(), '.tmp')) {
                yield ltrim(substr($file->getPathname(), \strlen($root)), '/');
            }
        }
    }

    public function url(string $key): string
    {
        return MediaKey::url($this->baseUrl, $key);
    }

    private function root(Visibility $visibility): string
    {
        return rtrim(Visibility::Public === $visibility ? $this->publicRoot : $this->privateRoot, '/');
    }

    private function path(Visibility $visibility, string $key): string
    {
        MediaKey::assertValid($key);

        return $this->root($visibility).'/'.$key;
    }
}
