<?php

declare(strict_types=1);

namespace App\Consumer\Handler;

use Alchemy\NotifyBundle\Notify\NotifierInterface;
use App\Entity\Asset;
use App\Entity\Commit;
use Arthem\Bundle\RabbitBundle\Consumer\Event\AbstractEntityManagerHandler;
use Arthem\Bundle\RabbitBundle\Consumer\Event\EventMessage;
use Arthem\Bundle\RabbitBundle\Consumer\Exception\ObjectNotFoundForHandlerException;
use Arthem\Bundle\RabbitBundle\Producer\EventProducer;

class CommitAcknowledgeHandler extends AbstractEntityManagerHandler
{
    final public const EVENT = 'commit_ack';

    public function __construct(private readonly MessageBusInterface $bus, private readonly NotifierInterface $notifier, private readonly int $deleteAssetGracefulTime)
    {
    }

    public function handle(EventMessage $message): void
    {
        $payload = $message->getPayload();
        $id = $payload['id'];

        $em = $this->getEntityManager();
        $commit = $em->find(Commit::class, $id);
        if (!$commit instanceof Commit) {
            throw new ObjectNotFoundForHandlerException(Commit::class, $id, self::class);
        }

        if ($commit->isAcknowledged()) {
            return;
        }

        $commit->setAcknowledged(true);

        $em->transactional(function () use ($em, $commit): void {
            $em->createQueryBuilder()
                ->update(Asset::class, 'a')
                ->set('a.acknowledged', ':true')
                ->andWhere('a.commit = :commit')
                ->setParameter('commit', $commit->getId())
                ->setParameter('true', true)
                ->getQuery()
                ->execute();

            $em->persist($commit);
            $em->flush();
        });

        if ($this->deleteAssetGracefulTime <= 0) {
            foreach ($commit->getAssets() as $asset) {
                $this->eventProducer->publish(new EventMessage(DeleteAssetFileHandler::EVENT, [
                    'path' => $asset->getPath(),
                ]));
            }
        } else {
            $this->eventProducer->publish(new EventMessage(DeleteExpiredAssetsHandler::EVENT, []));
        }

        if ($commit->getNotifyEmail()) {
            $this->notifier->sendEmail(
                $commit->getNotifyEmail(),
                'uploader/commit_acknowledged',
                $commit->getLocale() ?? 'en',
                [
                'asset_count' => $commit->getAssets()->count(),
            ]);
        }

        $this->notifier->notifyTopic(
            'upload_commit_acknowledged',
            'uploader/commit_acknowledged',
            [
                'asset_count' => $commit->getAssets()->count(),
            ]
        );
    }

    public static function getHandledEvents(): array
    {
        return [self::EVENT];
    }

    public static function getQueueName(): string
    {
        return 'fast_events';
    }
}
