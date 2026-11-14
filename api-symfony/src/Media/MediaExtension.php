<?php

declare(strict_types=1);

namespace App\Media;

use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class MediaExtension extends AbstractExtension
{
    public function __construct(private readonly MediaStorage $storage)
    {
    }

    public function getFunctions(): array
    {
        return [new TwigFunction('media_url', $this->storage->url(...))];
    }
}
