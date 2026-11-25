<?php

declare(strict_types=1);

namespace App\Consumer;

use App\Consumer\Handler\AssetConsumerNotify;
use App\Entity\Commit;
use App\Entity\CommitRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Notifies the target of a commit through AssetConsumerNotify, whose handler sends the commit to the target each
 * time: Commit::$notifyDispatchedAt records the dispatch, so that a retried commit dispatches it again only when
 * the dispatch failed.
 */
final readonly class CommitNotifier
{
    public function __construct(
        private MessageBusInterface $bus,
        private EntityManagerInterface $em,
    ) {
    }

    /**
     * Dispatches the notification unless it was dispatched already or the commit is acknowledged.
     *
     * The dispatch is marked before being sent, so that two requests storing or retrying the same commit at once
     * do not both send it, and unmarked when it fails.
     *
     * @return bool whether the notification was dispatched
     */
    public function notifyOnce(string $commitId): bool
    {
        if (!$this->getRepository()->claimNotifyDispatch($commitId)) {
            return false;
        }

        try {
            $this->bus->dispatch(new AssetConsumerNotify($commitId));
        } catch (\Throwable $e) {
            $this->getRepository()->setNotifyDispatchedAt($commitId, null);

            throw $e;
        }

        return true;
    }

    /**
     * Dispatches the notification again, as asked by an administrator.
     */
    public function notifyAgain(string $commitId): void
    {
        $this->bus->dispatch(new AssetConsumerNotify($commitId));
        $this->getRepository()->setNotifyDispatchedAt($commitId, new \DateTimeImmutable());
    }

    private function getRepository(): CommitRepository
    {
        /** @var CommitRepository $repository */
        $repository = $this->em->getRepository(Commit::class);

        return $repository;
    }
}
