<?php

declare(strict_types=1);

namespace App\Attribute\Type;

use Alchemy\CoreBundle\Util\DoctrineUtil;
use Alchemy\CoreBundle\Util\LocaleUtil;
use App\Api\Traits\UserLocaleTrait;
use App\Attribute\AttributeInterface;
use App\Elasticsearch\ESFacetInterface;
use App\Entity\Core\AttributeEntity;
use App\Repository\Core\AttributeEntityRepository;
use App\Security\Voter\AbstractVoter;
use App\Service\Image\ImageSetManager;
use Ramsey\Uuid\Uuid;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;

#[AsTaggedItem(index: self::NAME)]
final class EntityAttributeType extends TextAttributeType
{
    use UserLocaleTrait;

    public const string NAME = 'entity';

    public function __construct(
        private readonly AttributeEntityRepository $repository,
        private readonly Security $security,
        private readonly ImageSetManager $imageSetManager,
    ) {
    }

    #[\Override]
    public function supportsTranslations(): bool
    {
        return true;
    }

    #[\Override]
    public function validate(mixed $value): ?array
    {
        // The denormalized form (see denormalizeValue) carries the ID
        if (\is_array($value)) {
            $value = $value['id'] ?? null;
        }
        if (!is_string($value) || !Uuid::isValid($value)) {
            return ['Invalid entity ID'];
        }

        return null;
    }

    #[\Override]
    public function normalizeElasticsearchValue(?string $value): mixed
    {
        $entity = $this->getEntityFromValue($value);
        if (!$entity instanceof AttributeEntity || !$entity->isApproved()) {
            return null;
        }

        $locales = array_merge($entity->getTranslations() ?? [], [
            AttributeInterface::NO_LOCALE => $entity->getValue(),
        ]);
        $entityId = $entity->getId();

        $output = [];

        foreach ($locales as $locale => $v) {
            if (empty($v)) {
                continue;
            }

            $output[$locale] = [
                'id' => $entityId,
                'value' => $v,
            ];
        }

        $synonyms = $entity->getSynonyms();
        if (!empty($synonyms)) {
            foreach ($synonyms as $locale => $synonym) {
                if (empty($synonym)) {
                    continue;
                }

                $output[$locale] ??= [
                    'id' => $entityId,
                ];
                $output[$locale]['synonyms'] = $synonym;
            }
        }

        return $output;
    }

    public function getElasticSearchTextSubField(): string
    {
        return 'value';
    }

    #[\Override]
    public function getAdditionalSubFields(int $boost): array
    {
        return [
            'synonyms' => $boost * 0.5,
        ];
    }

    #[\Override]
    public function normalizeBuckets(array $buckets): array
    {
        $entities = DoctrineUtil::getIndexFromIds($this->repository, array_map(fn ($b) => $b['key'], $buckets));
        $this->imageSetManager->preload(array_map(fn (AttributeEntity $entity) => $entity->getImage(), array_values($entities)));

        return array_map(function (array $bucket) use ($entities): ?array {
            $entity = $entities[$bucket['key']] ?? null;
            if (null === $entity) {
                return null;
            }

            $translatedValue = $this->getTranslatedValue($entity);

            $bucket['key'] = [
                'value' => $bucket['key'],
                'label' => $translatedValue,
                'item' => [
                    ...$this->normalizeEntity($entity),
                    'translatedValue' => $translatedValue,
                ],
            ];

            return $bucket;
        }, $buckets);
    }

    private function normalizeEntity(AttributeEntity $entity): array
    {
        $canSeeValue = $this->canSeeValue($entity);

        return [
            'id' => $entity->getId(),
            'value' => $canSeeValue ? $this->getTranslatedValue($entity) : null,
            'note' => $entity->getNote(),
            'emoji' => $entity->getEmoji(),
            'color' => $entity->getColor(),
            'thumbnail' => $canSeeValue ? $this->imageSetManager->resolveThumbnailUrl($entity->getImage()) : null,
        ];
    }

    /**
     * A pending value is only shown to whoever may edit it: the managers of the list and its creator.
     */
    private function canSeeValue(AttributeEntity $entity): bool
    {
        return $entity->isApproved() || $this->security->isGranted(AbstractVoter::EDIT, $entity);
    }

    protected function getTranslatedValue(AttributeEntity $entity, ?string $locale = null): ?string
    {
        $translations = $entity->getTranslations() ?? [];
        $locale = LocaleUtil::getBestLocale(array_keys($translations), null !== $locale ? [$locale] : $this->getPreferredLocales($entity->getWorkspace()));

        return $translations[$locale] ?? $entity->getValue();
    }

    #[\Override]
    public function getAggregationField(): string
    {
        return 'id';
    }

    #[\Override]
    public function convertToDbValue(mixed $value): ?string
    {
        if ($value instanceof AttributeEntity) {
            return $value->getId();
        }

        return parent::convertToDbValue($value);
    }

    /**
     * Labels of the entity for the search suggestions: for each locale, the translation
     * (LocaleUtil::getBestLocale() rules) falling back to the base value. It does not depend on
     * the user locale, so it can run at index time.
     *
     * @param string[] $locales
     *
     * @return array<string, string> locale => label, empty when the entity is not approved
     */
    public function getSuggestionLabels(AttributeEntity $entity, array $locales): array
    {
        if (!$entity->isApproved()) {
            return [];
        }

        $labels = [];
        foreach ($locales as $locale) {
            $label = $this->getTranslatedValue($entity, $locale);
            if (null !== $label && '' !== $label) {
                $labels[$locale] = $label;
            }
        }

        return $labels;
    }

    public function getEntityFromValue(?string $value): ?AttributeEntity
    {
        if (null === $value) {
            return null;
        }

        $value = trim($value);
        if (empty($value)) {
            return null;
        }

        return $this->repository->find($value);
    }

    public function getEntityBestTranslation(?string $value, ?string $locale): ?string
    {
        $entity = $this->getEntityFromValue($value);
        if (!$entity instanceof AttributeEntity || !$entity->isApproved()) {
            return null;
        }

        return $this->getTranslatedValue($entity, $locale);
    }

    #[\Override]
    public function denormalizeValue(?string $value): mixed
    {
        $entity = $this->getEntityFromValue($value);
        if (!$entity instanceof AttributeEntity) {
            return null;
        }

        return [
            ...$this->normalizeEntity($entity),
            'status' => $entity->getStatus(),
            'createdAt' => $entity->getCreatedAt(),
        ];
    }

    #[\Override]
    public function getStringValue(?string $value, ?string $locale): string
    {
        return $this->getEntityBestTranslation($value, $locale) ?? '';
    }

    #[\Override]
    public function getElasticSearchMapping(string $locale): array
    {
        $mapping = parent::getElasticSearchMapping($locale);

        return [
            'type' => 'object',
            'properties' => [
                'id' => [
                    'type' => 'keyword',
                ],
                'value' => [
                    ...$mapping,
                    'type' => $this->getElasticSearchType(),
                ],
                'synonyms' => [
                    'type' => 'text',
                ],
            ],
        ];
    }

    #[\Override]
    public function getFacetType(): string
    {
        return ESFacetInterface::TYPE_ENTITY;
    }

    #[\Override]
    public function getElasticSearchRawField(): string
    {
        return 'id';
    }
}
