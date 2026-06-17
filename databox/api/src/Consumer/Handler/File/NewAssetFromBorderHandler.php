<?php

declare(strict_types=1);

namespace App\Consumer\Handler\File;

use Alchemy\CoreBundle\Util\DoctrineUtil;
use Alchemy\StorageBundle\Util\FileUtil;
use App\Entity\Core\Asset;
use App\Entity\Core\Collection;
use App\Entity\Core\File;
use App\Service\Asset\AssetManager;
use App\Service\Asset\Attribute\AssetNameFiller;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class NewAssetFromBorderHandler
{
    public function __construct(
        private AssetManager $assetManager,
        private EntityManagerInterface $em,
        private AssetNameFiller $assetNameFiller,
    ) {
    }

    public function __invoke(NewAssetFromBorder $message): void
    {
        $collectionIds = $message->getCollectionIds();
        $name = $message->getName();
        $filename = $message->getFilename();
        $formData = $message->getFormData();
        $locale = $message->getLocale();

        $file = DoctrineUtil::findStrict($this->em, File::class, $message->getFileId());

        $collections = $this->em->getRepository(Collection::class)->findByIds($collectionIds);

        $asset = new Asset();

        $asset->setSource($file);
        $asset->setOwnerId($message->getUserId());
        $asset->setWorkspace($file->getWorkspace());

        $name ??= ($filename ? FileUtil::stripExtension($filename) : null)
            ?? ($file->getPath() ? FileUtil::stripExtension($file->getPath()) : null);
        $this->assetNameFiller->fillName($asset, $name);

        foreach ($collections as $collection) {
            if (null === $asset->getReferenceCollection()) {
                $asset->setReferenceCollection($collection);
            }
            $assetCollection = $asset->addToCollection($collection);
            $this->em->persist($assetCollection);
        }

        $this->assetManager->assignNewAssetSourceFile($asset, $file, $formData, $locale);
        $this->em->flush();
    }
}
