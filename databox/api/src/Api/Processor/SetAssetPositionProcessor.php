<?php

declare(strict_types=1);

namespace App\Api\Processor;

use Alchemy\AuthBundle\Security\Traits\SecurityAwareTrait;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Api\Model\Input\AssetPositionInput;
use App\Entity\Core\Collection;
use App\Entity\Core\CollectionAsset;
use App\Repository\Core\CollectionAssetRepository;
use App\Security\Voter\AbstractVoter;
use App\Service\Collection\CollectionDestinationResolver;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Moves one asset to a given rank inside a collection or a story, shifting the
 * assets it steps over. Positions are kept dense (0..n-1), so only the relations
 * between the old and the new rank are rewritten.
 */
class SetAssetPositionProcessor implements ProcessorInterface
{
    use SecurityAwareTrait;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly CollectionDestinationResolver $destinationResolver,
    ) {
    }

    /**
     * @param AssetPositionInput $data
     */
    public function process($data, Operation $operation, array $uriVariables = [], array $context = []): Response
    {
        $collection = $this->destinationResolver->resolve($data->destination);
        $this->denyAccessUnlessGranted(AbstractVoter::EDIT, $collection);

        /** @var CollectionAssetRepository $repository */
        $repository = $this->em->getRepository(CollectionAsset::class);

        $collectionAsset = $repository->findCollectionAsset($uriVariables['id'], $collection->getId());
        if (!$collectionAsset instanceof CollectionAsset) {
            throw new NotFoundHttpException(sprintf('Asset "%s" is not part of %s', $uriVariables['id'], $this->describe($collection)));
        }

        $this->em->wrapInTransaction(function () use ($repository, $collection, $collectionAsset, $data): void {
            // Serializes the moves inside this collection: each one reads the order left by the previous one
            $this->em->lock($collection, LockMode::PESSIMISTIC_WRITE);

            $relations = $repository->findOrderedRelations($collection->getId());
            $currentIndex = null;
            foreach ($relations as $index => $relation) {
                if ($relation['id'] === $collectionAsset->getId()) {
                    $currentIndex = $index;
                    break;
                }
            }

            if (null === $currentIndex) {
                // Removed from the collection by a concurrent request
                throw new NotFoundHttpException(sprintf('Asset "%s" is not part of %s', $collectionAsset->getAsset()->getId(), $this->describe($collection)));
            }
            // Loaded before the lock: its position may have been changed since by a concurrent move
            $this->em->refresh($collectionAsset);

            $targetIndex = min(max($data->position, 0), count($relations) - 1);

            $moved = array_splice($relations, $currentIndex, 1);
            array_splice($relations, $targetIndex, 0, $moved);

            $newPositions = [];
            foreach ($relations as $index => $relation) {
                if ($relation['position'] !== $index) {
                    $newPositions[$relation['id']] = $index;
                }
            }

            if ([] === $newPositions) {
                return;
            }

            foreach ($repository->findBy(['id' => array_keys($newPositions)]) as $relation) {
                $relation->setPosition($newPositions[$relation->getId()]);
            }
        });

        return new Response('', 204);
    }

    private function describe(Collection $collection): string
    {
        $storyAsset = $collection->getStoryAsset();

        return null !== $storyAsset
            ? sprintf('story "%s"', $storyAsset->getId())
            : sprintf('collection "%s"', $collection->getId());
    }
}
