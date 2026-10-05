<?php

declare(strict_types=1);

namespace App\Service\Workspace\Template\Section;

use App\Entity\Core\AttributeDefinition;
use App\Entity\Core\Collection;
use App\Entity\Core\Tag;
use App\Entity\Core\Workspace;
use App\Entity\Template\AssetDataTemplate;
use App\Entity\Template\TemplateAttribute;
use App\Service\Workspace\Template\TemplateImportContext;
use App\Service\Workspace\Template\WorkspaceTemplateOptions;
use Doctrine\Common\Collections\ArrayCollection;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;

/**
 * Data templates belong to users: portable templates only carry the public ones.
 * The collection is kept only if it belongs to the target workspace (collections are content).
 */
#[AsTaggedItem(priority: 20)]
final class AssetDataTemplateSection extends AbstractTemplateSection
{
    public static function getKey(): string
    {
        return 'AssetDataTemplate';
    }

    public function export(Workspace $workspace, WorkspaceTemplateOptions $options): array
    {
        $o = [];
        foreach ($this->findByWorkspace(AssetDataTemplate::class, $workspace, ['name' => 'ASC']) as $item) {
            if (!$options->withAccessControl && !$item->isPublic()) {
                continue;
            }

            $o[] = [
                'id' => $item->getId(),
                'name' => $item->getName(),
                'public' => $item->isPublic(),
                'assetName' => $item->getAssetName(),
                'privacy' => $item->getPrivacy(),
                'collection' => $item->getCollectionId(),
                'includeCollectionChildren' => $item->isIncludeCollectionChildren(),
                'data' => $item->getData(),
                'tags' => $item->getTags()
                    ->map(fn (Tag $tag): string => $tag->getId())
                    ->getValues(),
                'attributes' => $item->getAttributes()
                    ->map(fn (TemplateAttribute $attribute): array => [
                        'definition' => $attribute->getDefinition()->getId(),
                        'value' => $attribute->getValue(),
                        'locale' => $attribute->getLocale(),
                        'position' => $attribute->getPosition(),
                    ])
                    ->getValues(),
                ...$this->exportOwnerId($item->getOwnerId(), $options),
            ];
        }

        return $o;
    }

    public function import(array $data, TemplateImportContext $context): void
    {
        $ws = $context->workspace;
        foreach ($data as $item) {
            $ownerId = $this->resolveOwnerId($item, $context);
            $o = $context->findExisting(AssetDataTemplate::class, [
                'workspace' => $ws,
                'name' => $item['name'],
                'ownerId' => $ownerId,
            ]);
            $this->logUpsert('AssetDataTemplate', $item['name'], null === $o);
            if (null === $o) {
                $o = new AssetDataTemplate();
                $o->setWorkspace($ws);
                $o->setName($item['name']);
                $o->setOwnerId($ownerId);
            }
            $o->setPublic($item['public'] ?? false);
            $o->setAssetName($item['assetName'] ?? null);
            $o->setPrivacy($item['privacy'] ?? null);
            $o->setCollection($this->resolveCollection($item, $context));
            $o->setIncludeCollectionChildren($item['includeCollectionChildren'] ?? false);
            $o->setData($context->remapIds($item['data'] ?? []));
            $o->setTags(new ArrayCollection(array_values(array_filter(array_map(
                fn (string $tagId): ?Tag => $context->get(TagSection::getKey(), $tagId, Tag::class),
                $item['tags'] ?? [],
            )))));

            foreach ($o->getAttributes()->toArray() as $attribute) {
                $o->removeAttribute($attribute);
                $this->em->remove($attribute);
            }
            foreach ($item['attributes'] ?? [] as $attributeItem) {
                $definition = $context->get(AttributeDefinitionSection::getKey(), $attributeItem['definition'], AttributeDefinition::class);
                if (null === $definition) {
                    $this->logger->warning(sprintf('Unknown AttributeDefinition "%s" in AssetDataTemplate "%s"', $attributeItem['definition'], $item['name']));
                    continue;
                }

                $attribute = new TemplateAttribute();
                $attribute->setDefinition($definition);
                $attribute->setValue($context->remapIds($attributeItem['value']));
                $attribute->setLocale($attributeItem['locale'] ?? null);
                $attribute->setPosition($attributeItem['position'] ?? 0);
                $o->addAttribute($attribute);
                $this->em->persist($attribute);
            }
            $this->em->persist($o);

            $context->register(self::getKey(), $item['id'] ?? null, $o);
        }
    }

    private function resolveCollection(array $item, TemplateImportContext $context): ?Collection
    {
        if (empty($item['collection'])) {
            return null;
        }

        $collection = $this->em->find(Collection::class, $context->remapIds($item['collection']));
        if ($collection instanceof Collection && $collection->getWorkspaceId() === $context->workspace->getId()) {
            return $collection;
        }

        $this->logger->warning(sprintf('Collection "%s" of AssetDataTemplate "%s" is not in the workspace', $item['collection'], $item['name']));

        return null;
    }
}
