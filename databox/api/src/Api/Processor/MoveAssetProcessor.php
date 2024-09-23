<?php

declare(strict_types=1);

namespace App\Api\Processor;

use Alchemy\AuthBundle\Security\Traits\SecurityAwareTrait;
use ApiPlatform\Api\IriConverterInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Api\Model\Input\MoveAssetInput;
use App\Consumer\Handler\Asset\AssetMove;
use App\Entity\Core\Asset;
use App\Security\Voter\AbstractVoter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;

class MoveAssetProcessor implements ProcessorInterface
{
    use SecurityAwareTrait;

    public function __construct(
        private readonly MessageBusInterface $bus,
        private readonly EntityManagerInterface $em,
        private readonly IriConverterInterface $iriConverter,
    ) {
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
            $this->bus->dispatch(new AssetMove($asset->getId(), $data->destination));
        }

        return new Response('', 204);
    }
}
