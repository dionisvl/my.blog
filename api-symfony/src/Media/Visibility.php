<?php

declare(strict_types=1);

namespace App\Media;

enum Visibility: string
{
    case Public = 'public';
    case Private = 'private';
}
