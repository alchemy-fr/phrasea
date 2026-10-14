<?php

declare(strict_types=1);

namespace App\Service\Workspace\Template\Section;

use App\Entity\Core\AttributeEntity;
use App\Entity\Core\EntityList;
use App\Entity\Core\Workspace;
use App\Service\Workspace\Template\TemplateImportContext;
use App\Service\Workspace\Template\WorkspaceTemplateOptions;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;

#[AsTaggedItem(priority: 150)]
final class EntityListSection extends AbstractTemplateSection
{
    public const string ENTITY_SECTION = 'AttributeEntity';

    private const array OPTIONS = [
        'allowNewValues' => ['isAllowNewValues', 'setAllowNewValues'],
        'approveNewValues' => ['isApproveNewValues', 'setApproveNewValues'],
        'withEmojis' => ['isWithEmojis', 'setWithEmojis'],
        'withColors' => ['isWithColors', 'setWithColors'],
        'withTranslations' => ['isWithTranslations', 'setWithTranslations'],
        'withSynonyms' => ['isWithSynonyms', 'setWithSynonyms'],
    ];

    public static function getKey(): string
    {
        return 'EntityList';
    }

    public function export(Workspace $workspace, WorkspaceTemplateOptions $options): array
    {
        $o = [];
        foreach ($this->findByWorkspace(EntityList::class, $workspace) as $list) {
            $item = [
                'id' => $list->getId(),
                'name' => $list->getName(),
                ...$this->exportOwnerId($list->getOwnerId(), $options),
            ];
            foreach (self::OPTIONS as $key => [$getter]) {
                $item[$key] = $list->$getter();
            }

            /** @var AttributeEntity[] $entities */
            $entities = $this->em->getRepository(AttributeEntity::class)->findBy([
                'list' => $list->getId(),
            ], ['position' => 'ASC']);
            $item['entities'] = array_map(fn (AttributeEntity $entity): array => [
                'id' => $entity->getId(),
                'value' => $entity->getValue(),
                'position' => $entity->getPosition(),
                'translations' => $entity->getTranslations(),
                'synonyms' => $entity->getSynonyms(),
                'externalId' => $entity->getExternalId(),
                'status' => $entity->getStatus(),
                'emoji' => $entity->getEmoji(),
                'color' => $entity->getColor(),
            ], $entities);

            $o[] = $item;
        }

        return $o;
    }

    public function import(array $data, TemplateImportContext $context): void
    {
        $ws = $context->workspace;
        foreach ($data as $item) {
            $list = $context->findExisting(EntityList::class, [
                'workspace' => $ws,
                'name' => $item['name'],
            ]);
            $created = null === $list;
            $this->logUpsert('EntityList', $item['name'], $created);
            if ($created) {
                $list = new EntityList();
                $list->setWorkspace($ws);
                $list->setName($item['name']);
                $list->setOwnerId($this->resolveOwnerId($item, $context));
            }
            foreach (self::OPTIONS as $key => [, $setter]) {
                if (array_key_exists($key, $item)) {
                    $list->$setter((bool) $item[$key]);
                }
            }
            $this->em->persist($list);
            $context->register(self::getKey(), $item['id'] ?? null, $list);

            foreach ($item['entities'] ?? [] as $entityItem) {
                $this->importEntity($list, $entityItem, $context);
            }
        }
    }

    private function importEntity(EntityList $list, array $item, TemplateImportContext $context): void
    {
        $entity = $context->findExisting(AttributeEntity::class, isset($item['externalId']) ? [
            'list' => $list,
            'externalId' => $item['externalId'],
        ] : null, [
            'list' => $list,
            'value' => $item['value'],
        ]);
        if (null === $entity) {
            $entity = new AttributeEntity();
            $entity->setWorkspace($context->workspace);
            $entity->setList($list);
        }

        $entity->setValue($item['value']);
        $entity->setPosition($item['position'] ?? 0);
        $entity->setTranslations($item['translations'] ?? null);
        $entity->setSynonyms($item['synonyms'] ?? null);
        if (array_key_exists('externalId', $item)) {
            $entity->setExternalId($item['externalId']);
        }
        if (array_key_exists('status', $item)) {
            $entity->setStatus($item['status']);
        }
        if (array_key_exists('emoji', $item)) {
            $entity->setEmoji($item['emoji']);
        }
        if (array_key_exists('color', $item)) {
            $entity->setColor($item['color']);
        }
        $this->em->persist($entity);

        $context->register(self::ENTITY_SECTION, $item['id'] ?? null, $entity);
    }
}
