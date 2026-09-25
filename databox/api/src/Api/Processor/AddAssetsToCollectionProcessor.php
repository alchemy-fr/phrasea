<?php

declare(strict_types=1);

namespace App\Api\Processor;

use Alchemy\AuthBundle\Security\Traits\SecurityAwareTrait;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Api\Model\Input\AddAssetsToCollectionInput;
use App\Entity\Core\Asset;
use App\Entity\Core\CollectionAsset;
use App\Security\Voter\AbstractVoter;
use App\Security\Voter\CollectionVoter;
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
        $alreadyIn = $this->em->getRepository(CollectionAsset::class)->findAssetIdsInCollection(
            $collection->getId(),
            array_map(fn (Asset $asset): string => $asset->getId(), $assets),
        );
        $storyAssetId = $collection->getStoryAsset()?->getId();

        foreach ($assets as $asset) {
            $this->denyAccessUnlessGranted(AbstractVoter::READ, $asset);

            if ($asset->getWorkspaceId() !== $collection->getWorkspaceId()) {
                throw new BadRequestHttpException(sprintf('Asset "%s" does not belong to the destination workspace', $asset->getId()));
            }
            if ($asset->getId() === $storyAssetId) {
                throw new BadRequestHttpException('Cannot add a story to itself');
            }

            // The endpoint is idempotent: silently skip assets already in the collection
            if (in_array($asset->getId(), $alreadyIn, true)) {
                continue;
            }

            $this->em->persist($asset->addToCollection($collection));
        }

        $this->em->flush();

        return new Response('', 204);
    }
}
