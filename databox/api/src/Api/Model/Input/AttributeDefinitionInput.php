<?php

declare(strict_types=1);

namespace App\Api\Model\Input;

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
     * @var bool
     */
    public $searchable;

    /**
     * @var bool
     */
    public $translatable;

    /**
     * @var bool
     */
    public $multiple;

    /**
     * @var bool
     */
    public $allowInvalid;

    /**
     * @var int
     */
    public $searchBoost;

    /**
     * Language-indexed fallbacks.
     * i.e: {"en":"English fallback","fr":"Valeur par défaut en français"}.
     *
     * @var string[]
     */
    public $fallback;

    /**
     * @var string
     */
    public $key;
}
