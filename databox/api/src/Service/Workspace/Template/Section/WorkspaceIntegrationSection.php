<?php

declare(strict_types=1);

namespace App\Service\Workspace\Template\Section;

use App\Entity\Core\Workspace;
use App\Entity\Integration\WorkspaceIntegration;
use App\Service\Workspace\Template\TemplateImportContext;
use App\Service\Workspace\Template\WorkspaceTemplateOptions;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;

/**
 * Configs may hold ids of rendition definitions (e.g. core.rendition): they are remapped.
 */
#[AsTaggedItem(priority: 70)]
final class WorkspaceIntegrationSection extends AbstractTemplateSection
{
    public static function getKey(): string
    {
        return 'WorkspaceIntegration';
    }

    public function export(Workspace $workspace, WorkspaceTemplateOptions $options): array
    {
        return array_map(fn (WorkspaceIntegration $item): array => [
            'id' => $item->getId(),
            'name' => $item->getName(),
            'integration' => $item->getIntegration(),
            'public' => $item->getPublic(),
            'enabled' => $item->isEnabled(),
            'if' => $item->getIf(),
            'config' => $item->getConfig(),
            'needs' => $item->getNeeds()
                ->map(fn (WorkspaceIntegration $need): string => $need->getId())
                ->getValues(),
            ...$this->exportOwnerId($item->getOwnerId(), $options),
        ], $this->findByWorkspace(WorkspaceIntegration::class, $workspace, ['integration' => 'ASC', 'name' => 'ASC']));
    }

    public function import(array $data, TemplateImportContext $context): void
    {
        $ws = $context->workspace;
        $imported = [];
        foreach ($data as $item) {
            $o = $context->findExisting(WorkspaceIntegration::class, [
                'workspace' => $ws,
                'integration' => $item['integration'],
                'name' => $item['name'],
            ]);
            $this->logUpsert('WorkspaceIntegration', $item['name'] ?? $item['integration'], null === $o);
            if (null === $o) {
                $o = new WorkspaceIntegration();
                $o->setWorkspace($ws);
                $o->setIntegration($item['integration']);
                $o->setName($item['name']);
                $o->setOwnerId($this->resolveOwnerId($item, $context));
            } elseif (isset($item['ownerId'])) {
                $o->setOwnerId($item['ownerId']);
            }
            $o->setPublic($item['public'] ?? false);
            $o->setEnabled($item['enabled'] ?? true);
            $o->setIf($item['if'] ?? null);
            $o->setConfig($context->remapIds($item['config'] ?? []));
            $this->em->persist($o);

            $context->register(self::getKey(), $item['id'] ?? null, $o);
            $imported[] = [$o, $item];
        }

        // needs may reference integrations that come later in the template
        foreach ($imported as [$o, $item]) {
            foreach ($o->getNeeds()->toArray() as $need) {
                $o->removeNeed($need);
            }
            foreach ($item['needs'] ?? [] as $needId) {
                $need = $context->get(self::getKey(), $needId, WorkspaceIntegration::class);
                if (null !== $need) {
                    $o->addNeed($need);
                } else {
                    $this->logger->warning(sprintf('Unknown needed WorkspaceIntegration "%s" for "%s"', $needId, $item['name'] ?? $item['integration']));
                }
            }
        }
    }
}
