<?php

declare(strict_types=1);

namespace App\Consumer\Handler;

use App\Entity\Asset;
use App\Entity\BulkData;
use App\Model\Commit;
use Arthem\Bundle\RabbitBundle\Consumer\Event\AbstractEntityManagerHandler;
use Arthem\Bundle\RabbitBundle\Consumer\Event\AbstractLogHandler;
use Arthem\Bundle\RabbitBundle\Consumer\Event\EventMessage;
use Arthem\Bundle\RabbitBundle\Producer\EventProducer;
use Doctrine\ORM\EntityManagerInterface;
use GuzzleHttp\Client;
use OldSound\RabbitMqBundle\RabbitMq\ConsumerInterface;

class CommitHandler extends AbstractEntityManagerHandler
{
    const EVENT = 'commit';

    /**
     * @var EventProducer
     */
    private $eventProducer;

    public function __construct(
        EventProducer $eventProducer
    ) {
        $this->eventProducer = $eventProducer;
    }

    public function handle(EventMessage $message): void
    {
        $commit = Commit::fromArray($message->getPayload());
        $commit->generateToken();

        $em = $this->getEntityManager();

        $bulkData = $em
            ->getRepository(BulkData::class)
            ->getBulkDataArray();

        $formData = array_merge($commit->getFormData(), $bulkData);

        $em
            ->getRepository(Asset::class)
            ->attachFormDataAndToken($commit->getFiles(), $formData, $commit->getToken());

        $this->eventProducer->publish(new EventMessage(AssetConsumerNotifyHandler::EVENT, [
            'files' => $commit->getFiles(),
            'user_id' => $commit->getUserId(),
            'token' => $commit->getToken(),
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
