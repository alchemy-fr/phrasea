<?php

declare(strict_types=1);

namespace App\Service\Workspace\Template\Section;

use App\Entity\Core\Workspace;
use App\Service\Workspace\Template\TemplateImportContext;
use App\Service\Workspace\Template\WorkspaceTemplateOptions;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;

/**
 * Name and slug are not part of the template: they are given on import.
 */
#[AsTaggedItem(priority: 160)]
final class WorkspaceSection extends AbstractTemplateSection
{
    public static function getKey(): string
    {
        return 'Workspace';
    }

    public function export(Workspace $workspace, WorkspaceTemplateOptions $options): array
    {
        return [
            'id' => $workspace->getId(),
            'public' => $workspace->isPublic(),
            'enabledLocales' => $workspace->getEnabledLocales(),
            'localeFallbacks' => $workspace->getLocaleFallbacks(),
            'config' => $workspace->getConfig(),
            'translations' => $workspace->getTranslations(),
        ];
    }

    public function import(array $data, TemplateImportContext $context): void
    {
        $ws = $context->workspace;
        $context->register(self::getKey(), $data['id'] ?? null, $ws);

        if (array_key_exists('public', $data)) {
            $ws->setPublic($data['public']);
        }
        if (array_key_exists('enabledLocales', $data)) {
            $ws->setEnabledLocales($data['enabledLocales']);
        }
        if (array_key_exists('localeFallbacks', $data)) {
            $ws->setLocaleFallbacks($data['localeFallbacks']);
        }
        if (array_key_exists('config', $data)) {
            $ws->setConfig($data['config']);
        }
        if (array_key_exists('translations', $data)) {
            $ws->setTranslations($data['translations']);
        }
        $this->em->persist($ws);
    }
}
