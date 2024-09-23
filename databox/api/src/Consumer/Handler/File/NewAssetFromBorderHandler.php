<?php

declare(strict_types=1);

namespace App\Consumer\Handler\File;

use Alchemy\CoreBundle\Util\DoctrineUtil;
use Alchemy\StorageBundle\Util\FileUtil;
use App\Asset\AssetManager;
use App\Entity\Core\Asset;
use App\Entity\Core\Collection;
use App\Entity\Core\File;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
readonly class NewAssetFromBorderHandler
{
    public function __construct(
        private AssetManager $assetManager,
        private EntityManagerInterface $em,
    ) {
    }

    public function __invoke(NewAssetFromBorder $message): void
    {
        $collectionIds = $message->getCollectionIds();
        $title = $message->getTitle();
        $filename = $message->getFilename();
        $formData = $message->getFormData();
        $locale = $message->getLocale();

        $file = DoctrineUtil::findStrict($this->em, File::class, $message->getFileId());

        $collections = $this->em->getRepository(Collection::class)->findByIds($collectionIds);

        $asset = new Asset();

        $asset->setSource($file);
        $asset->setOwnerId($message->getUserId());
        $asset->setTitle($title
            ?? ($filename ? FileUtil::stripExtension($filename) : null)
            ?? ($file->getPath() ? FileUtil::stripExtension($file->getPath()) : null));
        $asset->setWorkspace($file->getWorkspace());

        foreach ($collections as $collection) {
            $assetCollection = $asset->addToCollection($collection);
            $this->em->persist($assetCollection);
        }

        $this->assetManager->assignNewAssetSourceFile($asset, $file, $formData, $locale);
        $this->em->flush();
    }
}
