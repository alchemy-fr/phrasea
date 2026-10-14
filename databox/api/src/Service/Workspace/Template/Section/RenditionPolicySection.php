<?php

declare(strict_types=1);

namespace App\Service\Workspace\Template\Section;

use App\Entity\Core\RenditionPolicy;
use App\Entity\Core\Workspace;
use App\Service\Workspace\Template\TemplateImportContext;
use App\Service\Workspace\Template\WorkspaceTemplateOptions;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;

#[AsTaggedItem(priority: 130)]
final class RenditionPolicySection extends AbstractTemplateSection
{
    public static function getKey(): string
    {
        return 'RenditionPolicy';
    }

    public function export(Workspace $workspace, WorkspaceTemplateOptions $options): array
    {
        return array_map(fn (RenditionPolicy $item): array => [
            'id' => $item->getId(),
            'name' => $item->getName(),
            'public' => $item->isPublic(),
            'editable' => $item->isEditable(),
            'labels' => $item->getLabels(),
        ], $this->findByWorkspace(RenditionPolicy::class, $workspace));
    }

    public function import(array $data, TemplateImportContext $context): void
    {
        $ws = $context->workspace;
        foreach ($data as $item) {
            $o = $context->findExisting(RenditionPolicy::class, [
                'workspace' => $ws,
                'name' => $item['name'],
            ]);
            $this->logUpsert('RenditionPolicy', $item['name'], null === $o);
            if (null === $o) {
                $o = new RenditionPolicy();
                $o->setWorkspace($ws);
                $o->setName($item['name']);
            }
            $o->setPublic($item['public']);
            $o->setLabels($item['labels']);
            $o->setEditable($item['editable'] ?? true);
            $this->em->persist($o);

            $context->register(self::getKey(), $item['id'] ?? null, $o);
        }
    }
}
