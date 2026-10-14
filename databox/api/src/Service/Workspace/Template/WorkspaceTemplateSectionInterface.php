<?php

declare(strict_types=1);

namespace App\Service\Workspace\Template;

use App\Entity\Core\Workspace;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * One part of a workspace template (e.g. the tags, the attribute definitions).
 *
 * Sections are imported by decreasing priority (#[AsTaggedItem]): a section may only
 * reference entities registered in the import context by a section of higher priority.
 */
#[AutoconfigureTag(self::TAG)]
interface WorkspaceTemplateSectionInterface
{
    final public const string TAG = 'app.workspace_template_section';

    /**
     * Key of the section in the template data.
     */
    public static function getKey(): string;

    public function export(Workspace $workspace, WorkspaceTemplateOptions $options): array;

    public function import(array $data, TemplateImportContext $context): void;
}
