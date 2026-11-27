<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261127130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Rename aphorism.detail_text to detail_text_ru; add English text and internal source notes';
    }

    public function up(Schema $schema): void
    {
        $this->assertPostgres();

        $this->addSql("SET LOCAL lock_timeout = '1s'");
        $this->addSql('ALTER TABLE public.aphorism RENAME COLUMN detail_text TO detail_text_ru');
        $this->addSql('ALTER TABLE public.aphorism ADD COLUMN detail_text_en TEXT, ADD COLUMN source_en TEXT');
        $this->addSql(<<<'SQL'
            UPDATE public.aphorism a
            SET detail_text_en = v.en, source_en = v.source
            FROM (VALUES
              (5, 'Я хотел бы, чтобы смерть застала меня за работой в поле.', 'Let death take me planting my cabbages.', 'Adapted from Montaigne, Essays I.19, Charles Cotton''s translation (Project Gutenberg 3600); wording not checked verbatim.'),
              (6, 'Я мыслю, следовательно, существую.', 'I think, therefore I am.', 'Descartes, Discourse on Method IV, standard English rendering of “je pense, donc je suis”; wording not checked verbatim.'),
              (7, 'Я знаю только то, что ничего не знаю.', 'I neither know nor think that I know.', 'Adapted from Plato, Apology 21d, Benjamin Jowett''s translation (Project Gutenberg 1656); wording not checked verbatim. The Russian is a paraphrase.'),
              (8, 'Это долг – ради спасения истины отказаться даже от дорогого и близкого.', 'Piety requires us to honour truth above our friends.', 'Adapted from Aristotle, Nicomachean Ethics I.6, 1096a, W. D. Ross (1925); wording not checked verbatim.'),
              (832, 'Долог путь поучения, короток и успешен путь примеров.', 'The way is long if one follows precepts, but short and helpful, if one follows patterns.', 'Adapted from Seneca, Moral Epistles VI.5, Richard M. Gummere (1917); wording not checked verbatim.')
            ) AS v(id, ru, en, source)
            WHERE a.id = v.id AND a.detail_text_ru = v.ru
            SQL);
    }

    // Drops translations entered after this migration ran.
    public function down(Schema $schema): void
    {
        $this->assertPostgres();

        $this->addSql("SET LOCAL lock_timeout = '1s'");
        $this->addSql('ALTER TABLE public.aphorism DROP COLUMN detail_text_en, DROP COLUMN source_en');
        $this->addSql('ALTER TABLE public.aphorism RENAME COLUMN detail_text_ru TO detail_text');
    }

    private function assertPostgres(): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform,
            'This migration targets PostgreSQL only.',
        );
    }
}
