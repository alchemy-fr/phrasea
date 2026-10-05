<?php

declare(strict_types=1);

namespace App\Service\Workspace\Template\Section;

use App\Entity\Core\AttributeFilterRule;
use App\Entity\Core\Workspace;
use App\Service\Workspace\Template\TemplateImportContext;
use App\Service\Workspace\Template\WorkspaceTemplateOptions;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;

/**
 * UUIDs embedded in the AQL condition (@tag, @rendition, entity values, …) are remapped to the
 * imported entities. Other UUIDs (e.g. collections) are not part of a template: such rules fail closed.
 * Without the targets (portable templates), an imported rule applies to everyone.
 */
#[AsTaggedItem(priority: 60)]
final class AttributeFilterRuleSection extends AbstractTemplateSection
{
    public static function getKey(): string
    {
        return 'AttributeFilterRule';
    }

    public function export(Workspace $workspace, WorkspaceTemplateOptions $options): array
    {
        return array_map(fn (AttributeFilterRule $item): array => [
            'condition' => $item->getCondition(),
            ...($options->withAccessControl ? [
                'userIds' => $item->getUserIds(),
                'groupIds' => $item->getGroupIds(),
            ] : []),
        ], $this->findByWorkspace(AttributeFilterRule::class, $workspace));
    }

    public function import(array $data, TemplateImportContext $context): void
    {
        $ws = $context->workspace;
        foreach ($data as $item) {
            $condition = $context->remapIds($item['condition']);
            $o = $context->findExisting(AttributeFilterRule::class, [
                'workspace' => $ws,
                'condition' => $condition,
            ]);
            $this->logUpsert('AttributeFilterRule', $condition, null === $o);
            if (null === $o) {
                $o = new AttributeFilterRule();
                $o->setWorkspace($ws);
                $o->setCondition($condition);
            }
            if (array_key_exists('userIds', $item) || array_key_exists('groupIds', $item)) {
                $o->setTargets($item['userIds'] ?? [], $item['groupIds'] ?? []);
            }
            $this->em->persist($o);

            $context->register(self::getKey(), null, $o);
        }
    }
}
