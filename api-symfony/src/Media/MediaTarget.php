<?php

declare(strict_types=1);

namespace App\Media;

/** Where an entity's images live: the key prefix is shared by all drivers, DB rows keep only the file name. */
enum MediaTarget: string
{
    case Post = 'uploads';
    case Product = 'shop_uploads';
    case Portfolio = 'uploads/portfolio';

    public function key(string $filename): string
    {
        return $this->value.'/'.$filename;
    }
}
