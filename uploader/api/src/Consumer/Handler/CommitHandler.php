<?php

declare(strict_types=1);

namespace App\Consumer\Handler;

use App\Consumer\CommitNotifier;
use App\Entity\Commit;
use App\Storage\CommitManager;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class CommitHandler
{
    public function __construct(
        private CommitNotifier $commitNotifier,
        private CommitManager $commitManager,
        private EntityManagerInterface $em,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(CommitMessage $message): void
    {
        $commit = Commit::fromMessage($message, $this->em);

        $stored = $this->commitManager->store($commit);
        $this->em->clear();

        if (!$stored) {
            // Nothing to notify: the files went with another commit
            $this->logger->warning('Commit dropped: its files were committed meanwhile', ['files' => $message->getFiles()]);

            return;
        }

        $this->commitNotifier->notifyOnce($commit->getId());
    }
}
