<?php

declare(strict_types=1);

namespace App\Article\Exception;

use App\Article\Validation\ValidationIssue;

final class ArticleValidationException extends \DomainException
{
    /**
     * @param list<ValidationIssue> $issues
     */
    public function __construct(public readonly array $issues)
    {
        $first = $issues[0] ?? null;

        parent::__construct(null === $first
            ? 'Article validation failed.'
            : \sprintf('Article validation failed (%d issue(s)); first: %s at %s', \count($issues), $first->code, $first->path));
    }

    /**
     * @return list<string>
     */
    public function codes(): array
    {
        return array_values(array_unique(array_map(static fn (ValidationIssue $issue): string => $issue->code, $this->issues)));
    }
}
