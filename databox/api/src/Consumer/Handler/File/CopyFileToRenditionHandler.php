<?php

declare(strict_types=1);

namespace App\Consumer\Handler\File;

use App\Asset\FileCopier;
use App\Entity\Core\AssetRendition;
use App\Entity\Core\File;
use App\Util\DoctrineUtil;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class CopyFileToRenditionHandler
{
    public function __construct(
        private FileCopier $fileCopier,
        private EntityManagerInterface $em,
    ) {
    }

    public function __invoke(CopyFileToRendition $message): void
    {
        $rendition = DoctrineUtil::findStrict($this->em, AssetRendition::class, $message->getRenditionId());
        $file = DoctrineUtil::findStrict($this->em, File::class, $message->getFileId());

        $copy = $this->fileCopier->copyFile($file, $rendition->getAsset()->getWorkspace());

        $rendition->setFile($copy);

        $this->em->persist($rendition);
        $this->em->flush();
    }
}
