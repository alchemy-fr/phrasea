<?php

declare(strict_types=1);

namespace App\Consumer\Handler\File;

use Alchemy\CoreBundle\Util\DoctrineUtil;
use App\Entity\Core\Asset;
use App\Entity\Core\File;
use App\Service\Asset\FileCopier;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
readonly class CopyFileToAssetHandler
{
    public function __construct(
        private FileCopier $fileCopier,
        private EntityManagerInterface $em,
    ) {
    }

    public function __invoke(CopyFileToAsset $message): void
    {
        $asset = DoctrineUtil::findStrict($this->em, Asset::class, $message->getAssetId());
        $file = DoctrineUtil::findStrict($this->em, File::class, $message->getFileId());

        $copy = $this->fileCopier->copyFile($file, $asset->getWorkspace());

        $asset->setSource($copy);
        $asset->setNoFileVersion(true);

        $this->em->persist($asset);
        $this->em->flush();
    }
}
