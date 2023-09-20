<?php

declare(strict_types=1);

namespace App\Api\Processor;

use ApiPlatform\Api\IriConverterInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Api\Model\Input\MoveAssetInput;
use App\Consumer\Handler\Asset\AssetMoveHandler;
use App\Entity\Core\Asset;
use App\Security\Voter\AbstractVoter;
use App\Util\SecurityAwareTrait;
use Arthem\Bundle\RabbitBundle\Producer\EventProducer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Response;

class MoveAssetProcessor implements ProcessorInterface
{
    use SecurityAwareTrait;

    public function __construct(
        private readonly EventProducer $eventProducer,
        private readonly EntityManagerInterface $em,
        private readonly IriConverterInterface $iriConverter
    )
    {
    }

    /**
     * @param MoveAssetInput $data
     */
    public function process($data, Operation $operation, array $uriVariables = [], array $context = []): Response
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
