<?php

declare(strict_types=1);

namespace App\Controller\Core;

use ApiPlatform\Api\IriConverterInterface;
use App\Api\Model\Input\MoveAssetInput;
use App\Consumer\Handler\Asset\AssetMoveHandler;
use App\Entity\Core\Asset;
use App\Security\Voter\AbstractVoter;
use Arthem\Bundle\RabbitBundle\Producer\EventProducer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class MoveAssetsAction extends AbstractController
{
    public function __construct(
        private readonly EventProducer $eventProducer,
        private readonly EntityManagerInterface $em,
        private readonly IriConverterInterface $iriConverter
    ) {
    }

    public function __invoke(MoveAssetInput $data, Request $request)
    {
        $assets = $this->em->getRepository(Asset::class)
            ->findByIds($data->ids);

        $dest = $this->iriConverter->getResourceFromIri($data->destination);
        $this->denyAccessUnlessGranted(AbstractVoter::EDIT, $dest);

        foreach ($assets as $asset) {
            $this->denyAccessUnlessGranted(AbstractVoter::EDIT, $asset);
            $this->eventProducer->publish(AssetMoveHandler::createEvent($asset->getId(), $data->destination));
        }

        return new Response('', 204);
    }
}
