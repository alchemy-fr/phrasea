<?php

declare(strict_types=1);

namespace App\Consumer\Handler\File;

use Alchemy\CoreBundle\Util\DoctrineUtil;
use App\Entity\Core\File;
use App\Service\Asset\FileImporter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
readonly class ImportFileHandler
{
    public function __construct(
        private FileImporter $fileImporter,
        private EntityManagerInterface $em,
    ) {
    }

    public function __invoke(ImportFile $message): void
    {
        $this->fileImporter->importFile(
            DoctrineUtil::findStrict($this->em, File::class, $message->getFileId())
        );
    }
}
