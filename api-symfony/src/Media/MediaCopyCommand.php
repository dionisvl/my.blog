<?php

declare(strict_types=1);

namespace App\Media;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:media:copy', description: 'Copy media from the local storage to S3 (never deletes anything).')]
final class MediaCopyCommand extends Command
{
    private const array IMAGE_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp', 'avif', 'gif'];

    public function __construct(private readonly MediaStorageFactory $factory)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'List what would be copied, write nothing')
            ->addOption('reverse', null, InputOption::VALUE_NONE, 'Copy S3 to local instead (rollback after switching back to MEDIA_DRIVER=local)')
            ->addOption('verify', null, InputOption::VALUE_NONE, 'Read every copied object back and compare SHA-256');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dry = (bool) $input->getOption('dry-run');
        $verify = (bool) $input->getOption('verify');
        $reverse = (bool) $input->getOption('reverse');
        $source = $reverse ? $this->factory->s3() : $this->factory->local();
        $target = $reverse ? $this->factory->local() : $this->factory->s3();
        $stats = ['copied' => 0, 'skipped_present' => 0, 'skipped_type' => 0, 'failed' => 0];

        foreach ([Visibility::Public, Visibility::Private] as $visibility) {
            foreach ($source->keys($visibility) as $key) {
                if (!\in_array(strtolower(pathinfo($key, \PATHINFO_EXTENSION)), self::IMAGE_EXTENSIONS, true)) {
                    ++$stats['skipped_type'];
                    $io->writeln(\sprintf('skip (not an image) %s', $key));

                    continue;
                }

                $data = $source->get($visibility, $key);

                if (null === $data) {
                    ++$stats['failed'];

                    continue;
                }

                if ($target->exists($visibility, $key)) {
                    $remote = $target->get($visibility, $key);

                    if (null !== $remote && hash('sha256', $remote) === hash('sha256', $data)) {
                        ++$stats['skipped_present'];

                        continue;
                    }

                    $io->warning(\sprintf('%s exists in the target with different content; not overwritten', $key));
                    ++$stats['failed'];

                    continue;
                }

                if ($dry) {
                    $io->writeln(\sprintf('would copy %s/%s (%d bytes)', $visibility->value, $key, \strlen($data)));
                    ++$stats['copied'];

                    continue;
                }

                $tmp = tempnam(sys_get_temp_dir(), 'media');
                file_put_contents((string) $tmp, $data);

                try {
                    $target->put($visibility, $key, (string) $tmp, $this->contentType($key));

                    if ($verify) {
                        $remote = $target->get($visibility, $key);

                        if (null === $remote || hash('sha256', $remote) !== hash('sha256', $data)) {
                            $io->error(\sprintf('verify failed: %s/%s', $visibility->value, $key));
                            ++$stats['failed'];

                            continue;
                        }
                    }

                    ++$stats['copied'];
                } finally {
                    @unlink((string) $tmp);
                }
            }
        }

        $io->success(\sprintf('%s: copied %d, already present %d, skipped non-images %d, failed %d', $dry ? 'dry-run' : 'done', $stats['copied'], $stats['skipped_present'], $stats['skipped_type'], $stats['failed']));

        return 0 === $stats['failed'] ? Command::SUCCESS : Command::FAILURE;
    }

    private function contentType(string $key): string
    {
        return match (strtolower(pathinfo($key, \PATHINFO_EXTENSION))) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'webp' => 'image/webp',
            'avif' => 'image/avif',
            'gif' => 'image/gif',
            default => 'application/octet-stream',
        };
    }
}
