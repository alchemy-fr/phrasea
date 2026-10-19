<?php

declare(strict_types=1);

namespace App\Api\Processor;

use Alchemy\AuthBundle\Security\Traits\SecurityAwareTrait;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Api\Model\Input\AddAssetsToCollectionInput;
use App\Entity\Core\Asset;
use App\Security\Voter\AbstractVoter;
use App\Security\Voter\CollectionVoter;
use App\Service\Collection\AssetCollectionAdder;
use App\Service\Collection\CollectionDestinationResolver;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class AddAssetsToCollectionProcessor implements ProcessorInterface
{
    use SecurityAwareTrait;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly CollectionDestinationResolver $destinationResolver,
        private readonly AssetCollectionAdder $assetCollectionAdder,
    ) {
    }

    /**
     * @param AddAssetsToCollectionInput $data
     */
    public function process($data, Operation $operation, array $uriVariables = [], array $context = []): Response
    {
        $collection = $this->destinationResolver->resolve($data->destination);
        $this->denyAccessUnlessGranted(CollectionVoter::ASSET_CREATE, $collection);

        $assets = $this->em->getRepository(Asset::class)->findByIds($data->ids);

        foreach ($assets as $asset) {
            $this->denyAccessUnlessGranted(AbstractVoter::READ, $asset);

            if (null !== $reason = $this->assetCollectionAdder->getRefusalReason($collection, $asset)) {
                throw new BadRequestHttpException($reason);
            }
        }

        // The endpoint is idempotent: assets already in the collection are silently skipped
        $this->assetCollectionAdder->add($collection, $assets);

        return new Response('', 204);
    }
}
