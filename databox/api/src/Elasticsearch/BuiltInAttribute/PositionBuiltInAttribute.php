<?php

declare(strict_types=1);

namespace App\Elasticsearch\BuiltInAttribute;

use App\Attribute\Type\NumberAttributeType;
use App\Entity\Core\Asset;
use App\Entity\Core\Collection;
use App\Repository\Core\AssetRepository;
use App\Repository\Core\CollectionRepository;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * Rank of the asset inside the collection or the story being browsed.
 *
 * An asset holds one position per collection it belongs to, so this only sorts
 * when the search is narrowed down to a single container.
 */
final class PositionBuiltInAttribute extends AbstractBuiltInAttribute implements CustomSortBuiltInAttributeInterface
{
    public const string ES_FIELD = 'collectionPositions';

    public function __construct(
        private readonly CollectionRepository $collectionRepository,
        private readonly AssetRepository $assetRepository,
    ) {
    }

    public static function getName(): string
    {
        return self::ES_FIELD;
    }

    public static function getKey(): string
    {
        return '@position';
    }

    #[\Override]
    public function getType(): string
    {
        return NumberAttributeType::getName();
    }

    public function createSortClause(string $way, array $options): array
    {
        $collection = $this->resolveCollection($options);

        return [
            self::ES_FIELD.'.position' => [
                'order' => $way,
                'nested' => [
                    'path' => self::ES_FIELD,
                    'filter' => [
                        'term' => [
                            self::ES_FIELD.'.collection' => $collection->getId(),
                        ],
                    ],
                ],
            ],
        ];
    }

    private function resolveCollection(array $options): Collection
    {
        if (isset($options['story'])) {
            $storyAsset = $this->assetRepository->find($options['story']);
            if (!$storyAsset instanceof Asset || null === $storyCollection = $storyAsset->getStoryCollection()) {
                throw new BadRequestHttpException(sprintf('Story "%s" not found', $options['story']));
            }

            return $storyCollection;
        }

        $collectionIds = match (true) {
            isset($options['collection']) => [$options['collection']],
            isset($options['parent']) => [$options['parent']],
            default => $options['parents'] ?? [],
        };
        if (1 !== count($collectionIds)) {
            throw new BadRequestHttpException(sprintf('Sorting by "%s" requires the search to be narrowed down to a single collection ("collection" or "parent") or story ("story")', self::getKey()));
        }

        $collection = $this->collectionRepository->find(reset($collectionIds));
        if (!$collection instanceof Collection) {
            throw new BadRequestHttpException('Collection not found');
        }

        return $collection;
    }

    public function getValueFromAsset(Asset $asset): mixed
    {
        // Position is relative to a container: there is no absolute value to group or facet on.
        return null;
    }

    #[\Override]
    public function isFacet(): bool
    {
        return false;
    }

    #[\Override]
    public function isSearchable(): bool
    {
        return false;
    }

    protected function getAggregationTranslationKey(): string
    {
        return 'position';
    }
}
