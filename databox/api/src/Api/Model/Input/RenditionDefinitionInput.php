<?php

declare(strict_types=1);

namespace App\Api\Model\Input;

use App\Entity\Core\RenditionClass;
use App\Entity\Core\RenditionDefinition;
use App\Entity\Core\Workspace;
use Symfony\Component\Serializer\Attribute\Groups;

class RenditionDefinitionInput
{
    /**
     * @var Workspace|null
     */
    #[Groups([RenditionDefinition::GROUP_WRITE])]
    public $workspace;

    /**
     * @var RenditionDefinition|null
     */
    #[Groups([RenditionDefinition::GROUP_WRITE])]
    public $parent;

    /**
     * @var RenditionClass|null
     */
    #[Groups([RenditionDefinition::GROUP_WRITE])]
    public $class;

    /**
     * @var string
     */
    #[Groups([RenditionDefinition::GROUP_WRITE])]
    public $name;

    /**
     * @var bool
     */
    #[Groups([RenditionDefinition::GROUP_WRITE])]
    public $download;

    /**
     * @var int
     */
    #[Groups([RenditionDefinition::GROUP_WRITE])]
    public $buildMode;

    /**
     * @var bool
     */
    #[Groups([RenditionDefinition::GROUP_WRITE])]
    public $useAsOriginal;

    /**
     * @var bool
     */
    #[Groups([RenditionDefinition::GROUP_WRITE])]
    public $useAsPreview;

    /**
     * @var bool
     */
    #[Groups([RenditionDefinition::GROUP_WRITE])]
    public $useAsThumbnail;

    /**
     * @var bool
     */
    #[Groups([RenditionDefinition::GROUP_WRITE])]
    public $useAsThumbnailActive;

    /**
     * @var string|null
     */
    #[Groups([RenditionDefinition::GROUP_WRITE])]
    public $definition = '';

    /**
     * @var int|null
     */
    #[Groups([RenditionDefinition::GROUP_WRITE])]
    public $priority;

    /**
     * @var string|null
     */
    #[Groups([RenditionDefinition::GROUP_WRITE])]
    public $key;

    /**
     * @var array|null
     */
    #[Groups([RenditionDefinition::GROUP_WRITE])]
    public $labels;
}
