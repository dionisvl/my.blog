<?php

declare(strict_types=1);

namespace App\Article\Exception;

/**
 * Raised for slug collisions.
 * Story 3 maps it to HTTP 409.
 */
final class ArticleConflictException extends \RuntimeException
{
    public const string CODE_SLUG_TAKEN = 'slug_taken';

    public const string CODE_SLUG_AMBIGUOUS = 'slug_ambiguous';

    public function __construct(public readonly string $reason, string $message, ?\Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }
}
