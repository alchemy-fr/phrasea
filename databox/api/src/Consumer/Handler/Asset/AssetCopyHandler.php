<?php

declare(strict_types=1);

namespace App\Consumer\Handler\Asset;

use Alchemy\CoreBundle\Util\DoctrineUtil;
use ApiPlatform\Api\IriConverterInterface;
use App\Entity\Core\Asset;
use App\Entity\Core\Collection;
use App\Entity\Core\CollectionAsset;
use App\Entity\Core\Workspace;
use App\Service\Asset\AssetCopier;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
readonly class AssetCopyHandler
{
    public function __construct(
        private IriConverterInterface $iriConverter,
        private AssetCopier $assetCopier,
        private EntityManagerInterface $em,
    ) {
    }

    public function __invoke(AssetCopy $message): void
    {
        $link = $message->getLink() ?? false;
        $asset = DoctrineUtil::findStrict($this->em, Asset::class, $message->getId());

        /** @var Collection|Workspace $destination */
        $destination = $this->iriConverter->getResourceFromIri($message->getDestination());
        $destCollection = $destination instanceof Collection ? $destination : null;
        $destWorkspace = $destination instanceof Workspace ? $destination : $destination->getWorkspace();

        $link = $link && $destWorkspace->getId() === $asset->getWorkspaceId();

        if ($link) {
            if ($destCollection) {
                $collectionAsset = $this->em->getRepository(CollectionAsset::class)->findCollectionAsset(
                    $asset->getId(),
                    $destCollection->getId()
                );

                if (null === $collectionAsset) {
                    $asset->addToCollection($destCollection, extraMetadata: $message->getExtraMetadata());
                    $this->em->persist($asset);
                    $this->em->flush();
                }
            }
        } else {
            $this->assetCopier->copyAsset(
                $message->getUserId(),
                $message->getGroupsId() ?? [],
                $asset,
                $destWorkspace,
                $destCollection,
                $message->getOptions()
            );
        }
    }
}
