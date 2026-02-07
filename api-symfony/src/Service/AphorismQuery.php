<?php

declare(strict_types=1);

namespace App\Service;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;

final readonly class AphorismQuery
{
    public function __construct(
        private Connection $connection,
    ) {
    }

    /**
     * @return array{id: int, detail_text: string}|null
     */
    public function findRandom(): ?array
    {
        $count = (int)$this->connection->fetchOne('SELECT COUNT(id) FROM aphorism');

        if (0 === $count) {
            return null;
        }

        $offset = random_int(0, $count - 1);

        $row = $this->connection->fetchAssociative(
            'SELECT id, detail_text FROM aphorism ORDER BY id ASC LIMIT 1 OFFSET :offset',
            ['offset' => $offset],
            ['offset' => ParameterType::INTEGER],
        );

        if (false === $row) {
            return null;
        }

        return [
            'id' => (int)$row['id'],
            'detail_text' => (string)$row['detail_text'],
        ];
    }
}
