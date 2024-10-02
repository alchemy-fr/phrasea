<?php

declare(strict_types=1);

namespace App\Consumer\Handler\Asset;

use Alchemy\CoreBundle\Util\DoctrineUtil;
use App\Entity\Core\Asset;
use App\Entity\Core\CollectionAsset;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
readonly class AssetDeleteHandler
{
    public function __construct(
        private EntityManagerInterface $em,
    ) {
    }

    public function __invoke(AssetDelete $message): void
    {
        if (!empty($message->getCollections())) {
            $assetCollections = $this->em->getRepository(CollectionAsset::class)
                ->findBy(['asset' => $message->getIds(), 'collection' => $message->getCollections()]);
            foreach ($assetCollections as $assetCollection) {
                if ($assetCollection->getAsset()->getReferenceCollectionId() !== $assetCollection->getCollection()->getId()) {
                    $this->em->remove($assetCollection);
                }
            }
            $this->em->flush();

            return;
        }

        $assets = DoctrineUtil::iterateIds($this->em->getRepository(Asset::class), $message->getIds());
        foreach ($assets as $asset) {
            $this->em->remove($asset);
        }
        $this->em->flush();
    }
}
