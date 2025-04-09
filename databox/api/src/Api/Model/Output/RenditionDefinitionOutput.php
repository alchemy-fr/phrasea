<?php

declare(strict_types=1);

namespace App\Api\Model\Output;

use App\Api\Model\Output\Traits\CreatedAtDTOTrait;
use App\Api\Model\Output\Traits\UpdatedAtDTOTrait;
use App\Entity\Core\RenditionClass;
use App\Entity\Core\RenditionDefinition;
use App\Entity\Core\Workspace;
use Symfony\Component\Serializer\Annotation\Groups;

class RenditionDefinitionOutput extends AbstractUuidOutput
{
    use CreatedAtDTOTrait;
    use UpdatedAtDTOTrait;

    #[Groups([RenditionDefinition::GROUP_LIST])]
    public ?Workspace $workspace = null;

    #[Groups([RenditionDefinition::GROUP_LIST, RenditionDefinition::GROUP_READ])]
    public self|RenditionDefinition|null $parent = null;

    #[Groups([RenditionDefinition::GROUP_LIST, RenditionDefinition::GROUP_READ])]
    public ?string $name = null;

    #[Groups([RenditionDefinition::GROUP_LIST, RenditionDefinition::GROUP_READ])]
    public ?RenditionClass $class = null;

    #[Groups([RenditionDefinition::GROUP_LIST, RenditionDefinition::GROUP_READ])]
    public bool $download;

    #[Groups([RenditionDefinition::GROUP_LIST, RenditionDefinition::GROUP_READ])]
    public bool $substitutable;

    #[Groups([RenditionDefinition::GROUP_READ])]
    public ?array $labels = null;

    #[Groups([RenditionDefinition::GROUP_LIST, RenditionDefinition::GROUP_READ])]
    public int $buildMode;

    #[Groups([RenditionDefinition::GROUP_LIST, RenditionDefinition::GROUP_READ])]
    public bool $useAsOriginal = false;

    #[Groups([RenditionDefinition::GROUP_LIST, RenditionDefinition::GROUP_READ])]
    public bool $useAsPreview = false;

    #[Groups([RenditionDefinition::GROUP_LIST, RenditionDefinition::GROUP_READ])]
    public bool $useAsThumbnail = false;

    #[Groups([RenditionDefinition::GROUP_LIST, RenditionDefinition::GROUP_READ])]
    public bool $useAsThumbnailActive = false;

    #[Groups([RenditionDefinition::GROUP_LIST, RenditionDefinition::GROUP_READ])]
    public ?string $definition = null;

    #[Groups([RenditionDefinition::GROUP_LIST, RenditionDefinition::GROUP_READ])]
    public int $priority = 0;

    #[Groups([RenditionDefinition::GROUP_LIST, RenditionDefinition::GROUP_READ])]
    public ?string $nameTranslated = null;

    #[Groups([RenditionDefinition::GROUP_LIST, RenditionDefinition::GROUP_READ])]
    public ?array $translations = null;

}
