<?php

declare(strict_types=1);

namespace Alchemy\StorageBundle\Command;

use Alchemy\StorageBundle\Upload\UploadManager;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
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

    public function __invoke(
        SymfonyStyle $io,
        #[Option('Minimum age of the uploads to prune, in hours (default '.(UploadManager::PRUNE_MIN_AGE / 3600).', at least '.(UploadManager::PRUNE_MIN_AGE_LIMIT / 3600).', the lifetime of the part URLs)', name: 'min-age')]
        ?string $hours = null,
    ): int {
        $hours = null === $hours ? intdiv(UploadManager::PRUNE_MIN_AGE, 3600) : filter_var($hours, FILTER_VALIDATE_INT);
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
