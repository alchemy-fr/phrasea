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

class AssetAcknowledgeHandler extends AbstractEntityManagerHandler
{
    const EVENT = 'asset_ack';

    private EventProducer $eventProducer;

    public function __construct(EventProducer $eventProducer)
    {
        $this->eventProducer = $eventProducer;
    }

    public function handle(EventMessage $message): void
    {
        $payload = $message->getPayload();
        $id = $payload['id'];

        $em = $this->getEntityManager();
        $asset = $em->find(Asset::class, $id);
        if (!$asset instanceof Asset) {
            throw new ObjectNotFoundForHandlerException(Asset::class, $id, __CLASS__);
        }

        if ($asset->isAcknowledged()) {
            return;
        }

        $commit = $asset->getCommit();
        $unAckedCount = $em->getRepository(Asset::class)
            ->getUnacknowledgedAssetsCount($commit->getId());

        if (1 === $unAckedCount) {
            $this->eventProducer->publish(new EventMessage(CommitAcknowledgeHandler::EVENT, [
                'id' => $commit->getId(),
            ]));
        } else {
            $asset->setAcknowledged(true);
            $em->persist($asset);
            $em->flush();
        }
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
