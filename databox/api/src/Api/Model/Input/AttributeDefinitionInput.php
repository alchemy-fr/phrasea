<?php

declare(strict_types=1);

namespace App\Api\Model\Input;

use App\Entity\Core\AttributeClass;
use App\Entity\Core\Workspace;

class AttributeDefinitionInput
{
    public Workspace $workspace;

    public ?AttributeClass $class;

    /**
     * Target definition by name. Or use $definition.
     */
    public ?string $name;

    public string $fieldType;

    public ?string $entityType;

    public string $fileType;

    public bool $searchable;

    public bool $enabled;

    public bool $suggest;

    public bool $facetEnabled;

    public bool $sortable;

    public bool $translatable;

    public bool $multiple;

    public bool $allowInvalid;

    public int $searchBoost;

    /**
     * Language-indexed fallbacks.
     * i.e: {"en":"English fallback","fr":"Valeur par défaut en français"}.
     *
     * @var string[]
     */
    public array $fallback;

    /**
     * @var string[]
     */
    public array $initialValues;

    public ?string $key;

    public ?array $labels;

    public int $position;
}
