<?php

declare(strict_types=1);

namespace App\Media;

/** The uploaded file is not acceptable (type, size, dimensions, animation); a client error, not a server fault. */
final class MediaRejectedException extends \InvalidArgumentException
{
}
