<?php

declare(strict_types=1);

namespace App\Consumer\Handler\Asset;

use ApiPlatform\Core\Api\IriConverterInterface;
use App\Entity\Core\Asset;
use App\Entity\Core\Collection;
use App\Entity\Core\CollectionAsset;
use App\Entity\Core\Workspace;
use Arthem\Bundle\RabbitBundle\Consumer\Event\AbstractEntityManagerHandler;
use Arthem\Bundle\RabbitBundle\Consumer\Event\EventMessage;
use Arthem\Bundle\RabbitBundle\Consumer\Exception\ObjectNotFoundForHandlerException;

class AssetMoveHandler extends AbstractEntityManagerHandler
{
    const EVENT = 'asset_move';
    private IriConverterInterface $iriConverter;

    public function __construct(IriConverterInterface $iriConverter)
    {
        $this->iriConverter = $iriConverter;
    }

    public function handle(EventMessage $message): void
    {
        $payload = $message->getPayload();
        $id = $payload['id'];
        $dest = $payload['dest'];

        $em = $this->getEntityManager();
        $asset = $em->find(Asset::class, $id);
        if (!$asset instanceof Asset) {
            throw new ObjectNotFoundForHandlerException(Asset::class, $id, __CLASS__);
        }

        /** @var Collection|Workspace $destination */
        $destination = $this->iriConverter->getItemFromIri($dest);

        $em->wrapInTransaction(function () use ($em, $asset, $destination): void {
            $from = $asset->getReferenceCollection();

            if ($from instanceof Collection) {
                $em
                    ->getRepository(CollectionAsset::class)
                    ->deleteCollectionAsset($asset->getId(), $from->getId());
            }

            if ($destination instanceof Collection) {
                $em
                    ->getRepository(CollectionAsset::class)
                    ->deleteCollectionAsset($asset->getId(), $destination->getId());

                $asset->setReferenceCollection($destination);
                $collectionAsset = new CollectionAsset();
                $collectionAsset->setAsset($asset);
                $collectionAsset->setCollection($destination);
                $em->persist($collectionAsset);
            } else {
                $asset->setReferenceCollection(null);
            }

            $em->persist($asset);

            $em->flush();
        });
    }

    public static function getHandledEvents(): array
    {
        return [self::EVENT];
    }

    public static function createEvent(string $id, string $destination): EventMessage
    {
        return new EventMessage(self::EVENT, [
            'id' => $id,
            'dest' => $destination,
        ]);
    }
}
