<?php

declare(strict_types=1);

namespace App\Service\Workspace\Template\Section;

use App\Entity\Core\Workspace;
use App\Entity\Integration\WorkspaceSecret;
use App\Service\Workspace\Template\TemplateImportContext;
use App\Service\Workspace\Template\WorkspaceTemplateOptions;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;

/**
 * Values are encrypted with the instance key: they are only exported on demand,
 * and can only be imported back into the same instance.
 */
#[AsTaggedItem(priority: 80)]
final class WorkspaceSecretSection extends AbstractTemplateSection
{
    public static function getKey(): string
    {
        return 'WorkspaceSecret';
    }

    public function export(Workspace $workspace, WorkspaceTemplateOptions $options): array
    {
        return array_map(fn (WorkspaceSecret $item): array => [
            'name' => $item->getName(),
            ...($options->withSecrets ? ['value' => $item->getValue()] : []),
        ], $this->findByWorkspace(WorkspaceSecret::class, $workspace, ['name' => 'ASC']));
    }

    public function import(array $data, TemplateImportContext $context): void
    {
        $ws = $context->workspace;
        foreach ($data as $item) {
            $o = $context->findExisting(WorkspaceSecret::class, [
                'workspace' => $ws,
                'name' => $item['name'],
            ]);

            if (!isset($item['value'])) {
                if (null === $o) {
                    $this->logger->warning(sprintf('WorkspaceSecret "%s" has no value in the template and must be set manually', $item['name']));
                }
                continue;
            }

            $this->logUpsert('WorkspaceSecret', $item['name'], null === $o);
            if (null === $o) {
                $o = new WorkspaceSecret();
                $o->setWorkspace($ws);
                $o->setName($item['name']);
            }
            $o->setValue($item['value']);
            $this->em->persist($o);

            $context->register(self::getKey(), null, $o);
        }
    }
}
