<?php

declare(strict_types=1);

namespace App\Article\Exception;

/**
 * The requested operation does not apply to the current state (not a modular anchor,
 * nothing to publish, locale not published, ...).
 */
final class ArticleStateException extends \LogicException
{
}
