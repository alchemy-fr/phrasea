<?php

declare(strict_types=1);

namespace App\Service\Workspace\Template\Section;

use App\Entity\Core\AttributeDefinition;
use App\Entity\Core\AttributePolicy;
use App\Entity\Core\EntityList;
use App\Entity\Core\RenditionDefinition;
use App\Entity\Core\Workspace;
use App\Model\AssetTypeEnum;
use App\Service\Workspace\Template\TemplateImportContext;
use App\Service\Workspace\Template\WorkspaceTemplateOptions;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;

#[AsTaggedItem(priority: 110)]
final class AttributeDefinitionSection extends AbstractTemplateSection
{
    public static function getKey(): string
    {
        return 'AttributeDefinition';
    }

    public function export(Workspace $workspace, WorkspaceTemplateOptions $options): array
    {
        return array_map(fn (AttributeDefinition $item): array => [
            'id' => $item->getId(),
            'key' => $item->getKey(),
            'name' => $item->getName(),
            'slug' => $item->getSlug(),
            'policy' => $item->getPolicy()->getId(),
            'labels' => $item->getLabels(),
            'translations' => $item->getTranslations(),
            'entityList' => $item->getEntityList()?->getId(),
            'fallback' => $item->getFallback(),
            'type' => $item->getType(),
            'fileType' => $item->getFileType(),
            'initialValues' => $item->getInitialValues(),
            'readFromMetadata' => $item->getReadFromMetadata(),
            'writeMetadata' => $item->getWriteMetadata(),
            'writeMetadataRenditions' => $item->getWriteMetadataRenditions()
                ->map(fn (RenditionDefinition $rd): string => $rd->getId())
                ->getValues(),
            'position' => $item->getPosition(),
            'searchBoost' => $item->getSearchBoost(),
            'allowInvalid' => $item->isAllowInvalid(),
            'facetEnabled' => $item->isFacetEnabled(),
            'multiple' => $item->isMultiple(),
            'searchable' => $item->isSearchable(),
            'sortable' => $item->isSortable(),
            'suggest' => $item->isSuggest(),
            'translatable' => $item->isTranslatable(),
            'target' => $item->getTarget()->value,
            'enabled' => $item->isEnabled(),
            'editable' => $item->isEditable(),
            'guiEdit' => $item->isEditableInGui(),
            'fillFromName' => $item->isFillFromName(),
            'namePriority' => $item->getNamePriority(),
            'required' => $item->isRequired(),
            'maxLength' => $item->getMaxLength(),
            'minLength' => $item->getMinLength(),
        ], $this->findByWorkspace(AttributeDefinition::class, $workspace, ['position' => 'ASC', 'name' => 'ASC']));
    }

    public function import(array $data, TemplateImportContext $context): void
    {
        $ws = $context->workspace;
        foreach ($data as $item) {
            $o = $context->findExisting(AttributeDefinition::class, isset($item['key']) ? [
                'workspace' => $ws,
                'key' => $item['key'],
            ] : null, [
                'workspace' => $ws,
                'name' => $item['name'],
            ]);
            $this->logUpsert('AttributeDefinition', $item['name'], null === $o);
            if (null === $o) {
                $o = new AttributeDefinition();
                $o->setWorkspace($ws);
                // The slug is how Twig templates, AQL and integrations reference the attribute:
                // keep the exported one, which may differ from the slugified name.
                $o->setSlug($item['slug'] ?? null);
            }

            $o->setName($item['name']);
            if (array_key_exists('key', $item)) {
                $o->setKey($item['key']);
            }

            $policy = $context->get(AttributePolicySection::getKey(), $item['policy'], AttributePolicy::class);
            if (null === $policy) {
                throw new \InvalidArgumentException(sprintf('Unknown AttributePolicy "%s" for AttributeDefinition "%s"', $item['policy'], $item['name']));
            }
            $o->setPolicy($policy);
            $o->setLabels($item['labels']);
            if (array_key_exists('translations', $item)) {
                $o->setTranslations($item['translations']);
            }
            $o->setEntityList($context->get(EntityListSection::getKey(), $item['entityList'] ?? null, EntityList::class));
            $o->setFallback($item['fallback']);
            $o->setTarget(AssetTypeEnum::tryFrom((int) $item['target']) ?? AssetTypeEnum::Asset);
            // Support previous fieldType key for backward compatibility with older templates
            $o->setType($item['fieldType'] ?? $item['type']);
            $o->setFileType($item['fileType']);
            $o->setInitialValues($item['initialValues']);
            $o->setReadFromMetadata($item['readFromMetadata'] ?? null);
            $o->setWriteMetadata($item['writeMetadata'] ?? null);
            $o->setWriteMetadataRenditions($this->resolveRenditionDefinitions($item, $context));
            $o->setPosition($item['position']);
            $o->setSearchBoost($item['searchBoost']);
            $o->setAllowInvalid($item['allowInvalid']);
            $o->setFacetEnabled($item['facetEnabled']);
            $o->setMultiple($item['multiple']);
            $o->setSearchable($item['searchable']);
            $o->setSortable($item['sortable']);
            $o->setSuggest($item['suggest']);
            $o->setTranslatable($item['translatable']);
            $o->setEnabled($item['enabled'] ?? true);
            $o->setEditable($item['editable'] ?? true);
            $o->setEditableInGui($item['guiEdit']);
            $o->setFillFromName($item['fillFromName'] ?? false);
            $o->setNamePriority($item['namePriority'] ?? null);
            $o->setRequired($item['required'] ?? false);
            $o->setMaxLength($item['maxLength'] ?? null);
            $o->setMinLength($item['minLength'] ?? null);
            $this->em->persist($o);

            $context->register(self::getKey(), $item['id'] ?? null, $o);
        }
    }

    /**
     * References are rendition definition ids, or names in templates exported before.
     *
     * @return RenditionDefinition[]
     */
    private function resolveRenditionDefinitions(array $item, TemplateImportContext $context): array
    {
        $renditionDefinitions = [];
        foreach ($item['writeMetadataRenditions'] ?? [] as $ref) {
            $renditionDefinition = $context->get(RenditionDefinitionSection::getKey(), $ref, RenditionDefinition::class);
            if (null === $renditionDefinition) {
                foreach ($context->all(RenditionDefinitionSection::getKey()) as $rd) {
                    if ($rd instanceof RenditionDefinition && $rd->getName() === $ref) {
                        $renditionDefinition = $rd;
                        break;
                    }
                }
            }

            if (null !== $renditionDefinition) {
                $renditionDefinitions[] = $renditionDefinition;
            } else {
                $this->logger->warning(sprintf('Unknown RenditionDefinition "%s" in the writeMetadata scope of "%s"', $ref, $item['name']));
            }
        }

        return $renditionDefinitions;
    }
}
