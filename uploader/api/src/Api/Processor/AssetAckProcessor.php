<?php

declare(strict_types=1);

namespace App\Api\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Consumer\Handler\AssetAcknowledge;
use App\Entity\Asset;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Messenger\MessageBusInterface;

final readonly class AssetAckProcessor implements ProcessorInterface
{
    public function __construct(private MessageBusInterface $bus)
    {
    }

    /**
     * @param Asset $data
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): JsonResponse
    {
        $this->bus->dispatch(new AssetAcknowledge($data->getId()));

        return new JsonResponse(true);
    }
}
