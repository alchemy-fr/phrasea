<?php

declare(strict_types=1);

namespace App\Service\Workspace\Template\Section;

use App\Entity\Core\Workspace;
use App\Entity\Integration\WorkspaceEnv;
use App\Service\Workspace\Template\TemplateImportContext;
use App\Service\Workspace\Template\WorkspaceTemplateOptions;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;

#[AsTaggedItem(priority: 90)]
final class WorkspaceEnvSection extends AbstractTemplateSection
{
    public static function getKey(): string
    {
        return 'WorkspaceEnv';
    }

    public function export(Workspace $workspace, WorkspaceTemplateOptions $options): array
    {
        return array_map(fn (WorkspaceEnv $item): array => [
            'name' => $item->getName(),
            'value' => $item->getValue(),
        ], $this->findByWorkspace(WorkspaceEnv::class, $workspace, ['name' => 'ASC']));
    }

    public function import(array $data, TemplateImportContext $context): void
    {
        $ws = $context->workspace;
        foreach ($data as $item) {
            $o = $context->findExisting(WorkspaceEnv::class, [
                'workspace' => $ws,
                'name' => $item['name'],
            ]);
            $this->logUpsert('WorkspaceEnv', $item['name'], null === $o);
            if (null === $o) {
                $o = new WorkspaceEnv();
                $o->setWorkspace($ws);
                $o->setName($item['name']);
            }
            $o->setValue($item['value']);
            $this->em->persist($o);

            $context->register(self::getKey(), null, $o);
        }
    }
}
