<?php

declare(strict_types=1);

namespace App\Controller;

use App\Consumer\Handler\AssetAcknowledgeHandler;
use App\Entity\Asset;
use App\Security\Voter\AssetVoter;
use Arthem\Bundle\RabbitBundle\Consumer\Event\EventMessage;
use Arthem\Bundle\RabbitBundle\Producer\EventProducer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class AssetAckAction extends AbstractController
{
    public function __construct(private readonly EventProducer $eventProducer, private readonly EntityManagerInterface $em)
    {
    }

    public function __invoke(string $id)
    {
        $asset = $this->em->find(Asset::class, $id);
        if (null === $asset) {
            throw new NotFoundHttpException('Asset not found');
        }

        $this->denyAccessUnlessGranted(AssetVoter::ACK, $asset);

        $this->eventProducer->publish(new EventMessage(AssetAcknowledgeHandler::EVENT, [
            'id' => $asset->getId(),
        ]));

        return new JsonResponse(true);
    }
}
