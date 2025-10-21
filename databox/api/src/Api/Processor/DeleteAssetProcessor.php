<?php

declare(strict_types=1);

namespace App\Api\Processor;

use Alchemy\AuthBundle\Security\Traits\SecurityAwareTrait;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Consumer\Handler\Asset\AssetsDelete;
use App\Entity\Core\Asset;
use App\Repository\Core\AssetRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;

class DeleteAssetProcessor implements ProcessorInterface
{
    use SecurityAwareTrait;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly AssetRepository $assetRepository,
        private readonly MessageBusInterface $bus,
    ) {
    }

    /**
     * @param Asset $data
     */
    public function process($data, Operation $operation, array $uriVariables = [], array $context = []): Response
    {
        $this->bus->dispatch(new AssetsDelete([$data->getId()], [], true));

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }
}
