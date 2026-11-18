<?php

declare(strict_types=1);

namespace App\Consumer\Handler\File;

use Alchemy\CoreBundle\Util\DoctrineUtil;
use App\Entity\Core\File;
use App\Service\Asset\Exception\RemoteFileNotFoundException;
use App\Service\Asset\FileImporter;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
readonly class ImportFileHandler
{
    public function __construct(
        private FileImporter $fileImporter,
        private EntityManagerInterface $em,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(ImportFile $message): void
    {
        try {
            $this->fileImporter->importFile(
                DoctrineUtil::findStrict($this->em, File::class, $message->getFileId())
            );
        } catch (RemoteFileNotFoundException $e) {
            // Removed at the source: the file stays referenced by its URL
            $this->logger->warning($e->getMessage());
        }
    }
}
