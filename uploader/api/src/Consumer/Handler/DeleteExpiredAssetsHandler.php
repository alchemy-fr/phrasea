<?php

declare(strict_types=1);

namespace App\Consumer\Handler;

use App\Entity\Commit;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsMessageHandler]
final readonly class DeleteExpiredAssetsHandler
{
    public function __construct(
        private MessageBusInterface $bus,
        private EntityManagerInterface $em,
        private int $deleteAssetGracefulTime,
    ) {
    }

    public function __invoke(DeleteExpiredAssets $message): void
    {
        if ($this->deleteAssetGracefulTime <= 0) {
            return;
        }

        $date = (new \DateTimeImmutable())
            ->setTimestamp(time() - $this->deleteAssetGracefulTime);

        $commits = $this->em
            ->getRepository(Commit::class)
            ->getAcknowledgedBefore($date);

        foreach ($commits as $commit) {
            foreach ($commit->getAssets() as $asset) {
                $this->bus->dispatch(new DeleteAssetFile($asset->getPath()));
                $this->em->remove($asset);
            }

            $this->em->remove($commit);
            $this->em->flush();
        }
    }
}
