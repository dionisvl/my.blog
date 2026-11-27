<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261127120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Remove the unused Laravel-era posts.date column';
    }

    public function up(Schema $schema): void
    {
        $this->assertPostgres();

        $this->addSql('SET LOCAL lock_timeout = \'1s\'');
        $this->addSql('ALTER TABLE public.posts DROP COLUMN date');
    }

    // Restores the column only; the stored values are not recoverable from the schema.
    public function down(Schema $schema): void
    {
        $this->assertPostgres();

        $this->addSql('SET LOCAL lock_timeout = \'1s\'');
        $this->addSql('ALTER TABLE public.posts ADD COLUMN date DATE');
    }

    private function assertPostgres(): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform,
            'This migration targets PostgreSQL only.',
        );
    }
}
