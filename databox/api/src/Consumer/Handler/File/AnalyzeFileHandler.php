<?php

declare(strict_types=1);

namespace App\Consumer\Handler\File;

use Alchemy\CoreBundle\Util\DoctrineUtil;
use App\Border\FileAnalyzer;
use App\Entity\Core\File;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
readonly class AnalyzeFileHandler
{
    public function __construct(
        private FileAnalyzer $fileAnalyzer,
        private EntityManagerInterface $em,
    ) {
    }

    public function __invoke(AnalyzeFile $message): void
    {
        $file = DoctrineUtil::findStrict($this->em, File::class, $message->getFileId());

        $this->fileAnalyzer->analyzeFile($file);
        $this->em->persist($file);
        $this->em->flush();
    }
}
