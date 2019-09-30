<?php

declare(strict_types=1);

namespace App\Consumer\Handler;

use App\Entity\Asset;
use App\Entity\BulkData;
use App\Entity\Commit;
use App\Storage\AssetManager;
use Arthem\Bundle\RabbitBundle\Consumer\Event\AbstractEntityManagerHandler;
use Arthem\Bundle\RabbitBundle\Consumer\Event\EventMessage;
use Arthem\Bundle\RabbitBundle\Producer\EventProducer;
use Throwable;

class CommitHandler extends AbstractEntityManagerHandler
{
    const EVENT = 'commit';

    /**
     * @var EventProducer
     */
    private $eventProducer;
    /**
     * @var AssetManager
     */
    private $assetManager;

    public function __construct(EventProducer $eventProducer, AssetManager $assetManager)
    {
        $this->eventProducer = $eventProducer;
        $this->assetManager = $assetManager;
    }

    public function handle(EventMessage $message): void
    {
        $commit = Commit::fromArray($message->getPayload());
        $commit->generateToken();

        $totalSize = $this->assetManager->getTotalSize($commit->getFiles());
        $commit->setTotalSize($totalSize);

        $em = $this->getEntityManager();

        $bulkData = $em
            ->getRepository(BulkData::class)
            ->getBulkDataArray();

        $formData = array_merge($commit->getFormData(), $bulkData);
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
