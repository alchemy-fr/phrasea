<?php

declare(strict_types=1);

namespace App\Api\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\Post;
use ApiPlatform\State\ProcessorInterface;
use App\Border\Consumer\Handler\Uploader\UploaderNewCommit;
use App\Border\Model\Upload\IncomingUpload;
use Symfony\Component\Messenger\MessageBusInterface;

readonly class IncomingUploadProcessor implements ProcessorInterface
{
    public function __construct(private MessageBusInterface $bus)
    {
    }

    /**
     * @param IncomingUpload $data
     */
    public function process($data, Operation $operation, array $uriVariables = [], array $context = []): IncomingUpload
    {
        if ($operation instanceof Post) {
            $this->bus->dispatch(new UploaderNewCommit($data->toArray()));
        }

        return $data;
    }
}
