<?php

declare(strict_types=1);

namespace App\Api\Processor;

use Alchemy\AuthBundle\Security\Traits\SecurityAwareTrait;
use ApiPlatform\Metadata\IriConverterInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Api\Model\Input\MoveAssetInput;
use App\Consumer\Handler\Asset\AssetMove;
use App\Entity\Core\Asset;
use App\Entity\Core\Collection;
use App\Entity\Core\Workspace;
use App\Security\Voter\AbstractVoter;
use App\Security\Voter\CollectionVoter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
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
        $this->denyAccessUnlessGranted(CollectionVoter::ASSET_CREATE, $dest);

        $workspaceId = match (true) {
            $dest instanceof Collection => $dest->getWorkspaceId(),
            $dest instanceof Workspace => $dest->getId(),
            default => throw new BadRequestHttpException('The destination must be a collection or a workspace'),
        };

        // Validate every asset before moving any: the move is asynchronous
        foreach ($assets as $asset) {
            $this->denyAccessUnlessGranted(AbstractVoter::EDIT, $asset);

            if ($asset->getWorkspaceId() !== $workspaceId) {
                throw new BadRequestHttpException(sprintf('Asset "%s" cannot be moved to another workspace', $asset->getId()));
            }
        }

        foreach ($assets as $asset) {
            $this->bus->dispatch(new AssetMove($asset->getId(), $data->destination));
        }

        return new Response('', 204);
    }
}
