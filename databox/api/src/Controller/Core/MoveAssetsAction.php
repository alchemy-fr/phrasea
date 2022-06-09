<?php

declare(strict_types=1);

namespace App\Controller\Core;

use ApiPlatform\Core\Api\IriConverterInterface;
use App\Consumer\Handler\Asset\AssetMoveHandler;
use App\Entity\Core\Asset;
use App\Security\Voter\AssetVoter;
use App\Security\Voter\CollectionVoter;
use Arthem\Bundle\RabbitBundle\Producer\EventProducer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class MoveAssetsAction extends AbstractController
{
    private EventProducer $eventProducer;
    private EntityManagerInterface $em;
    private IriConverterInterface $iriConverter;

    public function __construct(
        EventProducer $eventProducer,
        EntityManagerInterface $em,
        IriConverterInterface $iriConverter
    ) {
        $this->eventProducer = $eventProducer;
        $this->em = $em;
        $this->iriConverter = $iriConverter;
    }

    public function __invoke(Asset $data, Request $request)
    {
        $data = $data->moveAction;

        $assets = $this->em->getRepository(Asset::class)
            ->findByIds($data->ids);

        $dest = $this->iriConverter->getItemFromIri($data->destination);
        $this->denyAccessUnlessGranted(CollectionVoter::EDIT, $dest);

        foreach ($assets as $asset) {
            $this->denyAccessUnlessGranted(AssetVoter::EDIT, $asset);
            $this->eventProducer->publish(AssetMoveHandler::createEvent($asset->getId(), $data->destination));
        }

        return new Response('', 204);
    }
}
