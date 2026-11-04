<?php

declare(strict_types=1);

namespace App\Service\Workspace\Template\Section;

use App\Entity\Core\AttributePolicy;
use App\Entity\Core\Workspace;
use App\Service\Workspace\Template\TemplateImportContext;
use App\Service\Workspace\Template\WorkspaceTemplateOptions;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;

#[AsTaggedItem(priority: 140)]
final class AttributePolicySection extends AbstractTemplateSection
{
    public static function getKey(): string
    {
        return 'AttributePolicy';
    }

    public function export(Workspace $workspace, WorkspaceTemplateOptions $options): array
    {
        return array_map(fn (AttributePolicy $item): array => [
            'id' => $item->getId(),
            'name' => $item->getName(),
            'key' => $item->getKey(),
            'editable' => $item->isEditable(),
            'public' => $item->isPublic(),
            'labels' => $item->getLabels(),
        ], $this->findByWorkspace(AttributePolicy::class, $workspace));
    }

    public function import(array $data, TemplateImportContext $context): void
    {
        $ws = $context->workspace;
        foreach ($data as $item) {
            $o = $context->findExisting(AttributePolicy::class, isset($item['key']) ? [
                'workspace' => $ws,
                'key' => $item['key'],
            ] : null, [
                'workspace' => $ws,
                'name' => $item['name'],
            ]);
            $this->logUpsert('AttributePolicy', $item['name'], null === $o);
            if (null === $o) {
                $o = new AttributePolicy();
                $o->setWorkspace($ws);
            }
            $o->setName($item['name']);
            if (array_key_exists('key', $item)) {
                $o->setKey($item['key']);
            }
            $o->setPublic($item['public']);
            $o->setLabels($item['labels']);
            $o->setEditable($item['editable']);
            $this->em->persist($o);

            $context->register(self::getKey(), $item['id'] ?? null, $o);
        }
    }
}
