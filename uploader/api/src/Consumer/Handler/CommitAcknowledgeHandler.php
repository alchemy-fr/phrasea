<?php

declare(strict_types=1);

namespace App\Consumer\Handler;

use App\Entity\Asset;
use App\Entity\Commit;
use Alchemy\AuthBundle\Security\JwtUser;
use Doctrine\ORM\EntityManagerInterface;
use Alchemy\CoreBundle\Util\DoctrineUtil;
use Symfony\Component\Messenger\MessageBusInterface;
use Alchemy\NotifyBundle\Notification\NotifierInterface;
use Alchemy\AuthBundle\Security\Traits\SecurityAwareTrait;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class CommitAcknowledgeHandler
{
    use SecurityAwareTrait;

    public function __construct(
        private MessageBusInterface $bus,
        private NotifierInterface $notifier,
        private EntityManagerInterface $em,
        private int $deleteAssetGracefulTime,
    ) {
    }

    public function __invoke(CommitAcknowledge $message): void
    {
        $commit = DoctrineUtil::findStrict($this->em, Commit::class, $message->getId());
        if ($commit->isAcknowledged()) {
            return;
        }

        $commit->setAcknowledged(true);

        $this->em->wrapInTransaction(function () use ($commit): void {
            $this->em->createQueryBuilder()
                ->update(Asset::class, 'a')
                ->set('a.acknowledged', ':true')
                ->andWhere('a.commit = :commit')
                ->setParameter('commit', $commit->getId())
                ->setParameter('true', true)
                ->getQuery()
                ->execute();

            $this->em->persist($commit);
            $this->em->flush();
        });

        if ($this->deleteAssetGracefulTime <= 0) {
            foreach ($commit->getAssets() as $asset) {
                $this->bus->dispatch(new DeleteAssetFile($asset->getPath()));
            }
        } else {
            $this->bus->dispatch(new DeleteExpiredAssets());
        }

        if ($commit->isNotify()) {
            $this->notifier->notifyUser(
                $commit->getUserId(),
                'uploader-commit-acknowledged',
                [
                    'assetCount' => $commit->getAssets()->count(),
                ]
            );
          
            $user = $this->getUser();
            if ($user instanceof JwtUser) {
                $this->notifier->sendEmail(
                    $user->getEmail(),
                    'uploader-commit-acknowledged',
                    [
                        'assetCount' => $commit->getAssets()->count(),
                    ]
                );
            } 
        }
    }
}
