<?php

declare(strict_types=1);

namespace App\Consumer\Handler;

use Alchemy\NotifyBundle\Notify\NotifierInterface;
use App\Entity\Commit;
use Arthem\Bundle\RabbitBundle\Consumer\Event\AbstractEntityManagerHandler;
use Arthem\Bundle\RabbitBundle\Consumer\Event\EventMessage;
use Arthem\Bundle\RabbitBundle\Consumer\Exception\ObjectNotFoundForHandlerException;
use Arthem\Bundle\RabbitBundle\Producer\EventProducer;

class CommitAcknowledgeHandler extends AbstractEntityManagerHandler
{
    const EVENT = 'commit_ack';

    /**
     * @var EventProducer
     */
    private $eventProducer;
    /**
     * @var NotifierInterface
     */
    private $notifier;

    public function __construct(EventProducer $eventProducer, NotifierInterface $notifier)
    {
        $this->eventProducer = $eventProducer;
        $this->notifier = $notifier;
    }

    public function handle(EventMessage $message): void
    {
        $payload = $message->getPayload();
        $id = $payload['id'];

        $em = $this->getEntityManager();
        $commit = $em->find(Commit::class, $id);
        if (!$commit instanceof Commit) {
            throw new ObjectNotFoundForHandlerException(Commit::class, $id, __CLASS__);
        }

        if ($commit->isAcknowledged()) {
            return;
        }

        $commit->setAcknowledged(true);
        $em->persist($commit);
        $em->flush();

        foreach ($commit->getAssets() as $asset) {
            $this->eventProducer->publish(new EventMessage(DeleteAssetFileHandler::EVENT, [
                'path' => $asset->getPath(),
            ]));
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
