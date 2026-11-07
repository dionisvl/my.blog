<?php

declare(strict_types=1);

namespace App\Article\Validation;

/**
 * Mutable state of a single ArticleDocumentValidator::validate() call.
 *
 * @internal
 */
final class ValidationRun
{
    public const int MAX_ISSUES = 50;

    /** @var list<ValidationIssue> */
    private array $issues = [];

    /** @var array<string, string> block id => block type */
    public array $blockTypes = [];

    /** @var array<string, list<string>> comparisonTable block id => column ids */
    public array $tableColumns = [];

    /** @var list<array{0: int, 1: array<string, mixed>}> cardSelector blocks, checked once all blocks are known */
    public array $selectors = [];

    public ?int $firstSponsoredBlock = null;

    public int $currentBlock = 0;

    public ?string $currentBlockType = null;

    public function __construct(public readonly bool $strict)
    {
    }

    public function add(string $path, string $code, string $message): void
    {
        if (\count($this->issues) < self::MAX_ISSUES) {
            $this->issues[] = new ValidationIssue($path, $code, $message);
        }
    }

    public function noteSponsoredLink(): void
    {
        $this->firstSponsoredBlock ??= $this->currentBlock;
    }

    /**
     * @return list<ValidationIssue>
     */
    public function issues(): array
    {
        return $this->issues;
    }
}
