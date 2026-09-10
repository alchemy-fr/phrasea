<?php

declare(strict_types=1);

namespace App\Elasticsearch\Listener;

use App\Attribute\AttributeInterface;
use App\Attribute\AttributeTypeRegistry;
use App\Attribute\Type\EntityAttributeType;
use App\Elasticsearch\AssetPermissionComputer;
use App\Elasticsearch\Mapping\FieldNameResolver;
use App\Entity\Core\Asset;
use App\Entity\Core\AssetRendition;
use App\Entity\Core\Attribute;
use App\Entity\Core\RenditionDefinition;
use App\Service\Asset\Attribute\AttributesResolver;
use App\Service\Asset\Attribute\Index\AttributeIndex;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query\Expr\Join;
use FOS\ElasticaBundle\Event\PostTransformEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

final readonly class AssetPostTransformListener implements EventSubscriberInterface
{
    /**
     * Longer values are not worth suggesting (kept in sync with the "ignore_above" of the mapping).
     */
    public const int SUGGESTION_MAX_LENGTH = 300;

    public function __construct(
        private AssetPermissionComputer $assetPermissionComputer,
        private AttributeTypeRegistry $attributeTypeRegistry,
        private FieldNameResolver $fieldNameResolver,
        private AttributesResolver $attributesResolver,
        private EntityManagerInterface $em,
    ) {
    }

    public function hydrateDocument(PostTransformEvent $event): void
    {
        /** @var Asset $asset */
        if (!($asset = $event->getObject()) instanceof Asset) {
            return;
        }

        $document = $event->getDocument();

        $permFields = $this->assetPermissionComputer->getAssetPermissionFields($asset);
        foreach ($permFields->toDocument() as $key => $value) {
            $document->set($key, $value);
        }

        $document->set('renditions', $this->compileRenditions($asset));

        $attributeIndex = $this->attributesResolver->resolveAssetAttributes($asset, false);

        $attrs = $this->compileAttributes($attributeIndex);
        // Wrap in an array to force replacing the whole field
        $document->set(AttributeInterface::ATTRIBUTES_FIELD, !empty($attrs) ? [$attrs] : null);

        $suggestions = $this->compileSuggestions($attributeIndex);
        $document->set(AttributeInterface::SUGGESTIONS_FIELD, !empty($suggestions) ? $suggestions : null);

        // Not ready yet
        //        $storyAttrs = [];
        //        foreach ($asset->getCollections() as $collectionAsset) {
        //            if (null !== $storyAsset = $collectionAsset->getCollection()->getStoryAsset()) {
        //                if (!isset($storyAttrs[$storyAsset->getId()])) {
        //                    $subAttrs = $this->compileAttributes($this->attributesResolver->resolveAssetAttributes($storyAsset, false));
        //                    if (!empty($subAttrs)) {
        //                        $storyAttrs[] = $subAttrs;
        //                    }
        //                }
        //            }
        //        }
        //        $document->set(AttributeInterface::STORY_ATTRIBUTES_FIELD, !empty($storyAttrs) ? $storyAttrs : null);
    }

    private function compileRenditions(Asset $asset): array
    {
        $renditionsDefinitions = $this->em->createQueryBuilder()
            ->select('rd.id')
            ->from(AssetRendition::class, 'r')
            ->innerJoin(RenditionDefinition::class, 'rd', Join::WITH, 'rd.id = r.definition')
            ->andWhere('r.asset = :id')
            ->setParameter('id', $asset->getId())
            ->getQuery()
            ->getScalarResult();

        return array_column($renditionsDefinitions, 'id');
    }

    private function compileAttributes(AttributeIndex $attributeIndex): array
    {
        $data = [];

        foreach ($attributeIndex->getDefinitions() as $definitionIndex) {
            $definition = $definitionIndex->getDefinition();
            $isMultiple = $definition->isMultiple();
            $type = $this->attributeTypeRegistry->getStrictType($definition->getType());
            $fieldName = null;

            foreach ($definitionIndex->getLocales() as $l => $a) {
                $v = null;
                if ($isMultiple) {
                    if (!empty($a)) {
                        $v = array_map(
                            fn (Attribute $v): mixed => $type->normalizeElasticsearchValue($v->getValue()),
                            array_filter($a, fn (Attribute $v): bool => !$v->isInvalid())
                        );
                    }
                } else {
                    assert($a instanceof Attribute);
                    if ($a->isInvalid()) {
                        continue;
                    }
                    $v = $a->getValue();
                    if (null !== $v) {
                        $v = $type->normalizeElasticsearchValue($v);
                    }
                }

                if (
                    null !== $v
                    && (!is_array($v) || !empty($v))
                ) {
                    $fieldName ??= $this->fieldNameResolver->getFieldNameFromDefinition($definition);

                    if ($type->supportsTranslations()) {
                        if ($isMultiple) {
                            foreach ($v as $item) {
                                if (is_array($item)) {
                                    foreach ($item as $locale => $translation) {
                                        $data[$locale][$fieldName] ??= [];
                                        $data[$locale][$fieldName][] = $translation;
                                    }
                                }
                            }
                        } else {
                            foreach ($v as $locale => $translation) {
                                $data[$locale][$fieldName] = $translation;
                            }
                        }
                    } else {
                        $data[$l][$fieldName] = $v;
                    }
                }
            }
        }

        return $data;
    }

    /**
     * Distinct values of the asset, one entry per (definition, value), for the search-as-you-type
     * suggestions (see SuggestionSearch). They are deduplicated per asset so that the suggestion
     * aggregation counts assets. Which definitions actually get suggested (the "suggest" flag,
     * the permissions) is decided at query time.
     */
    private function compileSuggestions(AttributeIndex $attributeIndex): array
    {
        $suggestions = [];

        foreach ($attributeIndex->getDefinitions() as $definitionIndex) {
            $definition = $definitionIndex->getDefinition();
            $type = $this->attributeTypeRegistry->getStrictType($definition->getType());
            if (!$type->supportsSuggest()) {
                continue;
            }

            $definitionId = $definition->getId();
            foreach ($definitionIndex->getFlattenAttributes() as $attribute) {
                if ($attribute->isInvalid()) {
                    continue;
                }

                $value = $attribute->getValue();
                $entityId = null;
                if ($type instanceof EntityAttributeType) {
                    // Suggest the entity label, not its ID
                    $entityId = $value;
                    $value = $type->normalizeElasticsearchValue($value)[AttributeInterface::NO_LOCALE]['value'] ?? null;
                }

                if (null === $value || '' === $value || mb_strlen($value) > self::SUGGESTION_MAX_LENGTH) {
                    continue;
                }

                $suggestion = [
                    'definitionId' => $definitionId,
                    'value' => $value,
                ];
                if (null !== $entityId) {
                    $suggestion['entityId'] = $entityId;
                }
                $suggestions[$definitionId.':'.$value] = $suggestion;
            }
        }

        return array_values($suggestions);
    }

    public static function getSubscribedEvents(): array
    {
        return [
            PostTransformEvent::class => 'hydrateDocument',
        ];
    }
}
