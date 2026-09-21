<?php

declare(strict_types=1);

namespace App\Service\Collection;

use ApiPlatform\Api\IriConverterInterface;
use App\Entity\Core\Asset;
use App\Entity\Core\Collection;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

final readonly class CollectionDestinationResolver
{
    public function __construct(
        private IriConverterInterface $iriConverter,
    ) {
    }

    public function resolve(string $iri): Collection
    {
        $destination = $this->iriConverter->getResourceFromIri($iri);

        if ($destination instanceof Collection) {
            return $destination;
        }

        if ($destination instanceof Asset) {
            return $this->resolveStoryCollection($destination);
        }

        throw new BadRequestHttpException('Destination must be a collection or a story');
    }

    public function resolveStoryCollection(Asset $storyAsset): Collection
    {
        $storyCollection = $storyAsset->getStoryCollection();
        if (!$storyCollection instanceof Collection) {
            throw new BadRequestHttpException(sprintf('Asset "%s" is not a story', $storyAsset->getId()));
        }

        return $storyCollection;
    }
}
