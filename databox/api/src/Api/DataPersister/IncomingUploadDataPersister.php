<?php

declare(strict_types=1);

namespace App\Api\DataPersister;

use ApiPlatform\Core\DataPersister\DataPersisterInterface;
use App\Border\Consumer\Handler\Uploader\UploaderNewCommitHandler;
use App\Border\Model\Upload\IncomingUpload;
use Arthem\Bundle\RabbitBundle\Consumer\Event\EventMessage;
use Arthem\Bundle\RabbitBundle\Producer\EventProducer;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class IncomingUploadDataPersister implements DataPersisterInterface
{
    private EventProducer $eventProducer;

    public function __construct(EventProducer $eventProducer)
    {
        $this->eventProducer = $eventProducer;
    }

    public function supports($data): bool
    {
        return $data instanceof IncomingUpload;
    }

    /**
     * @param IncomingUpload $data
     */
    public function persist($data)
    {
        $this->eventProducer->publish(new EventMessage(UploaderNewCommitHandler::EVENT, $data->toArray()));
    }

    public function remove($data)
    {
        throw new NotFoundHttpException('Not implemented');
    }
}
