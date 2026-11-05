<?php

declare(strict_types=1);

namespace App\Consumer\Handler\Asset;

use App\Consumer\Handler\Basket\BasketUpdate;
use App\Entity\Basket\BasketAsset;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * The basket items without a clip of their own publish the region of interest of their asset:
 * every basket holding the asset is updated. Its integrations compare the clip hashes, so
 * the items keeping their own clip are left untouched.
 */
#[AsMessageHandler]
final readonly class AssetRoiUpdateHandler
{
    public function __construct(
        private EntityManagerInterface $em,
        private MessageBusInterface $bus,
    ) {
    }

    public function __invoke(AssetRoiUpdate $message): void
    {
        /** @var list<array{id: string}> $rows */
        $rows = $this->em->createQueryBuilder()
            ->select('DISTINCT IDENTITY(ba.basket) AS id')
            ->from(BasketAsset::class, 'ba')
            ->andWhere('ba.asset = :asset')
            ->setParameter('asset', $message->assetId)
            ->getQuery()
            ->getArrayResult();

        foreach ($rows as $row) {
            $this->bus->dispatch(new BasketUpdate((string) $row['id']));
        }
    }
}
