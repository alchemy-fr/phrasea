<?php

declare(strict_types=1);

namespace App\Api\Model\Input;

use App\Entity\Core\AttributeClass;
use App\Entity\Core\EntityType;
use App\Entity\Core\Workspace;

class AttributeDefinitionInput
{
    /**
     * @var Workspace
     */
    public $workspace;

    /**
     * @var AttributeClass|null
     */
    public $class;

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
     * @var EntityType|null
     */
    public $entityType;

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
    public $enabled;

    /**
     * @var bool
     */
    public $suggest;

    /**
     * @var bool
     */
    public $facetEnabled;

    /**
     * @var bool
     */
    public $sortable;

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
     * @var string[]
     */
    public $initialValues;

    /**
     * @var string|null
     */
    public $key;

    /**
     * @var array|null
     */
    public $labels;

    /**
     * @var int
     */
    public $position;

    public ?array $translations = null;
}
