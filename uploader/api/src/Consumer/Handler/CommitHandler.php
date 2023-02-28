<?php

declare(strict_types=1);

namespace App\Consumer\Handler;

use App\Entity\Asset;
use App\Entity\Commit;
use App\Entity\TargetParams;
use App\Storage\AssetManager;
use Arthem\Bundle\RabbitBundle\Consumer\Event\AbstractEntityManagerHandler;
use Arthem\Bundle\RabbitBundle\Consumer\Event\EventMessage;
use Arthem\Bundle\RabbitBundle\Producer\EventProducer;
use Throwable;

class CommitHandler extends AbstractEntityManagerHandler
{
    const EVENT = 'commit';

    private EventProducer $eventProducer;
    private AssetManager $assetManager;

    public function __construct(EventProducer $eventProducer, AssetManager $assetManager)
    {
        $this->eventProducer = $eventProducer;
        $this->assetManager = $assetManager;
    }

    public function handle(EventMessage $message): void
    {
        $em = $this->getEntityManager();
        $commit = Commit::fromArray($message->getPayload(), $em);
        $commit->generateToken();
        $target = $commit->getTarget();

        $totalSize = $this->assetManager->getTotalSize($commit->getFiles());
        $commit->setTotalSize($totalSize);

        $targetParams = $em
            ->getRepository(TargetParams::class)
            ->findOneBy([
                'target' => $commit->getTarget()->getId(),
            ]);
        $targetData = $targetParams ? $targetParams->getData() : [];

        $formData = array_merge($commit->getFormData(), $targetData);
        if (!isset($formData['collection_destination']) && null !== $target->getDefaultDestination()) {
            $formData['collection_destination'] = $target->getDefaultDestination();
        }
        $commit->setFormData($formData);

        $em->beginTransaction();
        try {
            $em->persist($commit);
            $em->flush();
            $em
                ->getRepository(Asset::class)
                ->attachCommit($commit->getFiles(), $commit->getId());

            $em->commit();
        } catch (Throwable $e) {
            $em->rollback();
            throw $e;
        }

        $this->eventProducer->publish(new EventMessage(AssetConsumerNotifyHandler::EVENT, [
            'id' => $commit->getId(),
        ]));
    }

    public static function getHandledEvents(): array
    {
        return [self::EVENT];
    }

    public static function getQueueName(): string
    {
        return 'bulk_commit';
    }
}
