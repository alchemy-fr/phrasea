<?php

declare(strict_types=1);

namespace App\OperationTask\Task;

use App\Consumer\Handler\File\ImportFile;
use App\Entity\Core\File;
use App\OperationTask\OperationTaskInterface;
use App\OperationTask\RunContext;
use App\Repository\Core\FileRepository;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Dispatch an import for every file of a workspace still referenced by URL,
 * so its content gets fetched and stored on the main storage.
 */
#[AsTaggedItem(index: self::NAME)]
final readonly class ImportFilesTask implements OperationTaskInterface
{
    public const string NAME = 'import_files';

    public function __construct(
        private FileRepository $fileRepository,
        private MessageBusInterface $bus,
    ) {
    }

    public static function getName(): string
    {
        return self::NAME;
    }

    public function validate(array $payload): void
    {
        if (empty($payload['workspaceId'] ?? null)) {
            throw new BadRequestHttpException('workspaceId is required');
        }
    }

    public function handle(array $payload, RunContext $context): void
    {
        $dispatched = 0;
        foreach ($this->fileRepository->iterateIdsByWorkspace($payload['workspaceId'], File::STORAGE_URL) as $fileId) {
            $this->bus->dispatch(new ImportFile($fileId));
            ++$dispatched;
        }

        $context->getOutput()->writeln(sprintf('<info>Dispatched %d file(s) for import.</info>', $dispatched));
    }
}
