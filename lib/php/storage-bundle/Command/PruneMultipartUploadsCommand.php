<?php

declare(strict_types=1);

namespace Alchemy\StorageBundle\Command;

use Alchemy\StorageBundle\Upload\UploadManager;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'alchemy:storage:prune-multipart-uploads',
    description: 'Aborts the abandoned multipart uploads of the application on S3 and deletes the unused ones',
)]
final class PruneMultipartUploadsCommand extends Command
{
    public function __construct(
        private readonly UploadManager $uploadManager,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption(
            'min-age',
            null,
            InputOption::VALUE_REQUIRED,
            sprintf('Minimum age of the uploads to prune, in hours (at least %d, the lifetime of the part URLs)', UploadManager::PRUNE_MIN_AGE_LIMIT / 3600),
            UploadManager::PRUNE_MIN_AGE / 3600,
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $hours = filter_var($input->getOption('min-age'), FILTER_VALIDATE_INT);
        if (false === $hours) {
            $io->error('--min-age must be a whole number of hours');

            return Command::INVALID;
        }

        $minAge = $hours * 3600;
        if ($minAge < UploadManager::PRUNE_MIN_AGE_LIMIT) {
            $io->error(sprintf('--min-age must be at least %d hours', UploadManager::PRUNE_MIN_AGE_LIMIT / 3600));

            return Command::INVALID;
        }

        $result = $this->uploadManager->pruneParts($minAge);

        $io->success(sprintf('%d upload(s) aborted on S3, %d unused upload(s) deleted', $result['aborted'], $result['deleted']));

        return Command::SUCCESS;
    }
}
