<?php

declare(strict_types=1);

namespace App\Api\Model\Input;

use App\Entity\Core\Asset;
use App\Entity\Core\AttributeDefinition;
use App\Entity\Core\Workspace;

class AttributeDefinitionInput
{
    /**
     * @var Workspace
     */
    public $workspace = null;

    /**
     * Target definition by name. Or use $definition.
     *
     * @var string|null
     */
    public $name;

    /**
     * @var string
     */
    public $fieldType;

    /**
     * @var string
     */
    public $fileType;

    /**
     * @var boolean
     */
    public $editable;

    /**
     * @var boolean
     */
    public $searchable;

    /**
     * @var boolean
     */
    public $translatable;

    /**
     * @var boolean
     */
    public $multiple;

    /**
     * @var boolean
     */
    public $allowInvalid;

    /**
     * @var boolean
     */
    public $public;

    /**
     * @var int
     */
    public $searchBoost;

    /**
     * Language-indexed fallbacks.
     * i.e: {"en":"English fallback","fr":"Valeur par défaut en français"}
     *
     * @var string[]
     */
    public $fallback;

    /**
     * @var string
     */
    public $key;
}
