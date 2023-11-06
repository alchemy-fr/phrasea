<?php

declare(strict_types=1);

namespace App\Api\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\Post;
use ApiPlatform\State\ProcessorInterface;
use App\Border\Consumer\Handler\Uploader\UploaderNewCommitHandler;
use App\Border\Model\Upload\IncomingUpload;
use Arthem\Bundle\RabbitBundle\Consumer\Event\EventMessage;
use Arthem\Bundle\RabbitBundle\Producer\EventProducer;

readonly class IncomingUploadProcessor implements ProcessorInterface
{
    public function __construct(private EventProducer $eventProducer)
    {
    }

    /**
     * @param IncomingUpload $data
     */
    public function process($data, Operation $operation, array $uriVariables = [], array $context = []): IncomingUpload
    {
        if ($operation instanceof Post) {
            $this->eventProducer->publish(new EventMessage(UploaderNewCommitHandler::EVENT, $data->toArray()));
        }

        return $data;
    }
}
