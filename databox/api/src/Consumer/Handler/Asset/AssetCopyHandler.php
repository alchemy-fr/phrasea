<?php

declare(strict_types=1);

namespace App\Consumer\Handler\Asset;

use ApiPlatform\Core\Api\IriConverterInterface;
use App\Asset\AssetCopier;
use App\Entity\Core\Asset;
use App\Entity\Core\Collection;
use App\Entity\Core\CollectionAsset;
use App\Entity\Core\Workspace;
use Arthem\Bundle\RabbitBundle\Consumer\Event\AbstractEntityManagerHandler;
use Arthem\Bundle\RabbitBundle\Consumer\Event\EventMessage;
use Arthem\Bundle\RabbitBundle\Consumer\Exception\ObjectNotFoundForHandlerException;

class AssetCopyHandler extends AbstractEntityManagerHandler
{
    const EVENT = 'asset_copy';
    private IriConverterInterface $iriConverter;
    private AssetCopier $assetCopier;

    public function __construct(IriConverterInterface $iriConverter, AssetCopier $assetCopier)
    {
        $this->iriConverter = $iriConverter;
        $this->assetCopier = $assetCopier;
    }

    public function handle(EventMessage $message): void
    {
        $payload = $message->getPayload();
        $id = $payload['id'];
        $dest = $payload['dest'];
        $userId = $payload['userId'];
        $groupsId = $payload['groupsId'] ?? [];
        $link = $payload['link'] ?? false;
        $options = $payload['options'] ?? [];

        $em = $this->getEntityManager();
        $asset = $em->find(Asset::class, $id);
        if (!$asset instanceof Asset) {
            throw new ObjectNotFoundForHandlerException(Asset::class, $id, __CLASS__);
        }

        /** @var Collection|Workspace $destination */
        $destination = $this->iriConverter->getItemFromIri($dest);
        $destCollection = $destination instanceof Collection ? $destination : null;
        $destWorkspace = $destination instanceof Workspace ? $destination : $destination->getWorkspace();

        $link = $link && $destWorkspace->getId() === $asset->getWorkspaceId();

        if ($link) {
            $collectionAsset = $em->getRepository(CollectionAsset::class)->findCollectionAsset(
                $asset->getId(),
                $destination->getId()
            );

            if (null === $collectionAsset) {
                $asset->addToCollection($destination);
                $em->persist($asset);
                $em->flush();
            }
        } else {
            $this->assetCopier->copyAsset(
                $userId,
                $groupsId,
                $asset,
                $destWorkspace,
                $destCollection,
                $options
            );
        }
    }

    public static function getHandledEvents(): array
    {
        return [self::EVENT];
    }

    public static function createEvent(string $userId, array $groupsId, string $id, string $destination, ?bool $link = null, array $options = []): EventMessage
    {
        return new EventMessage(self::EVENT, [
            'id' => $id,
            'userId' => $userId,
            'groupsId' => $groupsId,
            'dest' => $destination,
            'link' => $link,
            'options' => $options,
        ]);
    }
}
