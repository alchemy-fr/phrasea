<?php

declare(strict_types=1);

namespace App\Consumer\Handler;

use Alchemy\CoreBundle\Util\DoctrineUtil;
use App\Entity\Asset;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsMessageHandler]
final readonly class AssetAcknowledgeHandler
{
    final public const EVENT = 'asset_ack';

    public function __construct(
        private MessageBusInterface $bus,
        private EntityManagerInterface $em,
    )
    {
    }

    public function __invoke(AssetAcknowledge $message): void
    {
        $asset = DoctrineUtil::findStrict($this->em, Asset::class, $message->getId());
        if ($asset->isAcknowledged()) {
            return;
        }

        $commit = $asset->getCommit();
        $unAckedCount = $this->em->getRepository(Asset::class)
            ->getUnacknowledgedAssetsCount($commit->getId());

        if (1 === $unAckedCount) {
            $this->bus->dispatch(new CommitAcknowledge($commit->getId()));
        } else {
            $asset->setAcknowledged(true);
            $this->em->persist($asset);
            $this->em->flush();
        }
    }
}
