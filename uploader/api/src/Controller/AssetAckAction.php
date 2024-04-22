<?php

declare(strict_types=1);

namespace App\Controller;

use App\Consumer\Handler\AssetAcknowledgeHandler;
use App\Entity\Asset;
use App\Security\Voter\AssetVoter;
use Arthem\Bundle\RabbitBundle\Consumer\Event\EventMessage;
use Arthem\Bundle\RabbitBundle\Producer\EventProducer;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;

final class AssetAckAction extends AbstractController
{
    public function __construct(private readonly MessageBusInterface $bus)
    {
    }

    public function __invoke(Asset $asset)
    {
        $this->denyAccessUnlessGranted(AssetVoter::ACK, $asset);

        $this->eventProducer->publish(new EventMessage(AssetAcknowledgeHandler::EVENT, [
            'id' => $asset->getId(),
        ]));

        return new JsonResponse(true);
    }
}
