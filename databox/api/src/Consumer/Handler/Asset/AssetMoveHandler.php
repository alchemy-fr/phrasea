<?php

declare(strict_types=1);

namespace App\Consumer\Handler\Asset;

use Alchemy\CoreBundle\Util\DoctrineUtil;
use ApiPlatform\Api\IriConverterInterface;
use App\Entity\Core\Asset;
use App\Entity\Core\Collection;
use App\Entity\Core\CollectionAsset;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;

#[AsMessageHandler]
readonly class AssetMoveHandler
{
    public function __construct(
        private IriConverterInterface $iriConverter,
        private EntityManagerInterface $em,
    ) {
    }

    public function __invoke(AssetMove $message): void
    {
        $dest = $message->getDestination();

        $asset = DoctrineUtil::findStrict($this->em, Asset::class, $message->getId());
        $destination = $this->iriConverter->getResourceFromIri($dest);
        // Checked by MoveAssetProcessor: an asset never leaves its workspace
        if ($destination instanceof Collection && $destination->getWorkspaceId() !== $asset->getWorkspaceId()) {
            throw new UnrecoverableMessageHandlingException(sprintf('Asset "%s" cannot be moved to another workspace', $asset->getId()));
        }

        $this->em->wrapInTransaction(function () use ($asset, $destination): void {
            $from = $asset->getReferenceCollection();

            if ($from instanceof Collection) {
                $this->em
                    ->getRepository(CollectionAsset::class)
                    ->deleteCollectionAsset($asset->getId(), $from->getId());
            }

            if ($destination instanceof Collection) {
                $this->em
                    ->getRepository(CollectionAsset::class)
                    ->deleteCollectionAsset($asset->getId(), $destination->getId());

                $asset->setReferenceCollection($destination);
                $collectionAsset = new CollectionAsset();
                $collectionAsset->setAsset($asset);
                $collectionAsset->setCollection($destination);
                $this->em->persist($collectionAsset);
            } else {
                $asset->setReferenceCollection(null);
            }

            $this->em->persist($asset);
            $this->em->flush();
        });
    }
}
