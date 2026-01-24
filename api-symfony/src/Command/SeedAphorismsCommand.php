<?php

declare(strict_types=1);

namespace App\Command;

use Doctrine\DBAL\Connection;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[AsCommand(
    name: 'app:seed-aphorisms',
    description: 'Seeds sample aphorisms for local development.',
)]
final class SeedAphorismsCommand extends Command
{
    public function __construct(
        private readonly Connection $connection,
        #[Autowire('%kernel.environment%')]
        private readonly string $environment,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('count', null, InputOption::VALUE_REQUIRED, 'How many aphorisms to ensure exist.', 12)
            ->addOption('force', null, InputOption::VALUE_NONE, 'Allow seeding outside dev/test environments.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        if (!\in_array($this->environment, ['dev', 'test'], true) && !$input->getOption('force')) {
            $io->error('Seeding is only allowed in dev/test. Use --force to override.');

            return Command::FAILURE;
        }

        $targetCount = max(1, (int)$input->getOption('count'));
        $currentCount = (int)$this->connection->fetchOne('SELECT COUNT(id) FROM aphorism');

        if ($currentCount >= $targetCount) {
            $io->success(\sprintf('Aphorisms already present: %d.', $currentCount));

            return Command::SUCCESS;
        }

        $samples = [
            'Small steps compound into large results.',
            'Write code for humans first, machines second.',
            'Bugs are just undocumented features with bad timing.',
            'A clean commit is a gift to your future self.',
            'Make it work, then make it clear, then make it fast.',
            'The best cache is the one you can invalidate.',
            'Simple beats clever when the pager goes off.',
            'Consistency is a feature, not a limitation.',
            'Tests buy courage for refactors.',
            'A slow query today becomes an outage tomorrow.',
            'Measure twice, deploy once.',
            'Edge cases are where quality lives.',
            'If it is hard to explain, it is hard to maintain.',
            'Good logs make good sleep.',
        ];

        $now = (new \DateTimeImmutable())->format('Y-m-d H:i:s');
        $inserted = 0;

        while ($currentCount < $targetCount) {
            $text = $samples[$currentCount % \count($samples)];
            $this->connection->insert('aphorism', [
                'detail_text' => $text,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            ++$currentCount;
            ++$inserted;
        }

        $io->success(\sprintf('Inserted %d aphorism(s).', $inserted));

        return Command::SUCCESS;
    }
}
