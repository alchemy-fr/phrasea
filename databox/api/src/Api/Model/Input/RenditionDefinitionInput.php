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
     * @var Workspace
     */
    #[Groups([RenditionDefinition::GROUP_WRITE])]
    public $workspace;

    /**
     * @var RenditionClass
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
    public $download = true;

    /**
     * @var bool
     */
    #[Groups([RenditionDefinition::GROUP_WRITE])]
    public $pickSourceFile = false;

    /**
     * @var bool
     */
    #[Groups([RenditionDefinition::GROUP_WRITE])]
    public $useAsOriginal = false;

    /**
     * @var bool
     */
    #[Groups([RenditionDefinition::GROUP_WRITE])]
    public $useAsPreview = false;

    /**
     * @var bool
     */
    #[Groups([RenditionDefinition::GROUP_WRITE])]
    public $useAsThumbnail = false;

    /**
     * @var bool
     */
    #[Groups([RenditionDefinition::GROUP_WRITE])]
    public $useAsThumbnailActive = false;

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
