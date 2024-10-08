<?php

declare(strict_types=1);

namespace App\Controller;

use App\Consumer\Handler\AssetAcknowledge;
use App\Entity\Asset;
use App\Security\Voter\AssetVoter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Messenger\MessageBusInterface;

final class AssetAckAction extends AbstractController
{
    public function __construct(private readonly MessageBusInterface $bus)
    {
    }

    public function __invoke(Asset $asset): JsonResponse
    {
        $this->denyAccessUnlessGranted(AssetVoter::ACK, $asset);

        $this->bus->dispatch(new AssetAcknowledge($asset->getId()));

        return new JsonResponse(true);
    }
}
