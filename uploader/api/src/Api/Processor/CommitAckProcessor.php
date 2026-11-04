<?php

declare(strict_types=1);

namespace App\Api\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Consumer\Handler\CommitAcknowledge;
use App\Entity\Commit;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Messenger\MessageBusInterface;

final readonly class CommitAckProcessor implements ProcessorInterface
{
    public function __construct(private MessageBusInterface $bus)
    {
    }

    /**
     * @param Commit $data
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): JsonResponse
    {
        if (!$data->isAcknowledged()) {
            $this->bus->dispatch(new CommitAcknowledge($data->getId()));
        }

        return new JsonResponse(true);
    }
}
