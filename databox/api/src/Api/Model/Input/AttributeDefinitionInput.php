<?php

declare(strict_types=1);

namespace App\Api\Model\Input;

use App\Attribute\Type\EntityAttributeType;
use App\Entity\Core\AttributePolicy;
use App\Entity\Core\EntityList;
use App\Entity\Core\Workspace;
use App\Validator\TwigConstraint;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

class AttributeDefinitionInput
{
    /**
     * @var Workspace
     */
    public $workspace;

    /**
     * @var AttributePolicy|null
     */
    public $policy;

    /**
     * Target definition by name. Or use $definition.
     *
     * @var string|null
     */
    public $name;

    /**
     * @var string
     */
    public $type;

    /**
     * @var EntityList|null
     */
    public $entityList;

    /**
     * @var string
     */
    public $fileType;

    public ?bool $fillFromName = null;

    public ?int $namePriority = null;

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
    public $editableInGui;

    /**
     * @var bool
     */
    public $editable;

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
    #[Assert\All([
        new TwigConstraint(),
    ])]
    public $fallback;

    /**
     * Language-indexed Twig templates resolved to initialize the attribute.
     *
     * @var string[]
     */
    #[Assert\All([
        new TwigConstraint(),
    ])]
    public $initialValues;

    /**
     * Ordered list of metadata tag names to read to initialize the attribute (first found wins).
     *
     * @var string[]|null
     */
    public ?array $readFromMetadata = null;

    /**
     * List of metadata tag names into which the attribute value is written on export.
     *
     * @var string[]|null
     */
    public ?array $writeMetadata = null;

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

    public ?int $target = null;

    #[Assert\Callback()]
    public function validate(ExecutionContextInterface $context)
    {
        if (EntityAttributeType::NAME === $this->type && !$this->entityList) {
            $context
                ->buildViolation('Missing entity list')
                ->atPath('entityList')
                ->addViolation();
        }
    }
}
