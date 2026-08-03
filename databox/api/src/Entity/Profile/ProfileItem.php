<?php

declare(strict_types=1);

namespace App\Entity\Profile;

use Alchemy\CoreBundle\Entity\AbstractUuidEntity;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\Put;
use App\Api\Model\Input\ProfileItemInput;
use App\Api\Model\Output\ProfileItemOutput;
use App\Api\Processor\PutProfileItemProcessor;
use App\Entity\Core\AttributeDefinition;
use App\Security\Voter\AbstractVoter;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

#[ORM\Entity]
#[ApiResource(
    uriTemplate: '/profiles/{id}/items',
    operations: [
        new GetCollection(),
        new Put(
            uriTemplate: '/profiles/{id}/items/{itemId}',
            security: 'is_granted("'.AbstractVoter::EDIT.'", object.getProfile())',
            processor: PutProfileItemProcessor::class,
        ),
    ],
    uriVariables: [
        'id' => new Link(toProperty: 'profile', fromClass: Profile::class),
        'itemId' => new Link(fromClass: ProfileItem::class),
    ],
    normalizationContext: [
        'groups' => [
            Profile::GROUP_READ,
        ],
    ],
    input: ProfileItemInput::class,
    output: ProfileItemOutput::class,
    order: ['position' => 'ASC'],
)]
#[ORM\UniqueConstraint(name: 'profile_def_uniq', columns: ['profile_id', 'section', 'definition_id', 'key', 'type'])]
class ProfileItem extends AbstractUuidEntity
{
    final public const int SECTION_ATTRIBUTES = 0;
    final public const int SECTION_FACETS = 1;
    final public const int SECTION_GRID = 2;

    final public const int TYPE_ATTR_DEF = 0;
    final public const int TYPE_BUILT_IN = 1;
    final public const int TYPE_DIVIDER = 2;
    final public const int TYPE_SPACER = 3;

    /**
     * Grid card regions (stored in options.placement.region).
     */
    final public const string REGION_OVER = 'over';
    final public const string REGION_BELOW = 'below';

    final public const array REGIONS = [
        self::REGION_OVER,
        self::REGION_BELOW,
    ];

    /**
     * Valid anchors per region (stored in options.placement.anchor).
     */
    final public const array ANCHORS = [
        self::REGION_OVER => ['tl', 'tc', 'tr', 'ml', 'cc', 'mr', 'bl', 'bc', 'br'],
        self::REGION_BELOW => ['l', 'c', 'r'],
    ];

    final public const array TYPES = [
        'Attribute Definition' => self::TYPE_ATTR_DEF,
        'Built-in' => self::TYPE_BUILT_IN,
        'Divider' => self::TYPE_DIVIDER,
        'Spacer' => self::TYPE_SPACER,
    ];
    final public const array SECTIONS = [
        'Attributes' => self::SECTION_ATTRIBUTES,
        'Facets' => self::SECTION_FACETS,
        'Grid' => self::SECTION_GRID,
    ];

    #[ORM\ManyToOne(targetEntity: Profile::class, inversedBy: 'items')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Profile $profile = null;

    #[ORM\Column(type: Types::SMALLINT, nullable: false)]
    #[Assert\Choice(choices: self::SECTIONS)]
    private ?int $section = null;

    #[ORM\Column(type: Types::SMALLINT, nullable: false)]
    #[Assert\Choice(choices: self::TYPES)]
    private int $type = self::TYPE_ATTR_DEF;

    #[ORM\ManyToOne(targetEntity: AttributeDefinition::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
    private ?AttributeDefinition $definition = null;

    #[ORM\Column(type: Types::STRING, length: 150, nullable: true)]
    private ?string $key = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $options = [];

    #[ORM\Column(type: Types::INTEGER, nullable: false)]
    private ?int $position = 0;

    public function getProfile(): ?Profile
    {
        return $this->profile;
    }

    public function setProfile(?Profile $profile): void
    {
        $this->profile = $profile;
    }

    public function getPosition(): ?int
    {
        return $this->position;
    }

    public function setPosition(int $position): void
    {
        $this->position = $position;
    }

    public function getDefinition(): ?AttributeDefinition
    {
        return $this->definition;
    }

    public function setDefinition(?AttributeDefinition $definition): void
    {
        $this->definition = $definition;
    }

    public function getKey(): ?string
    {
        return $this->key;
    }

    public function setKey(?string $key): void
    {
        $this->key = $key;
    }

    public function getSection(): ?int
    {
        return $this->section;
    }

    public function setSection(?int $section): void
    {
        $this->section = $section;
    }

    public function getType(): int
    {
        return $this->type;
    }

    public function setType(int $type): void
    {
        $this->type = $type;
    }

    public function isDisplayEmpty(): bool
    {
        return $this->options['displayEmpty'] ?? false;
    }

    public function setDisplayEmpty(bool $displayEmpty): void
    {
        if (!$displayEmpty) {
            unset($this->options['displayEmpty']);

            return;
        }

        $this->options['displayEmpty'] = $displayEmpty;
    }

    public function getFormat(): ?string
    {
        return $this->options['format'] ?? null;
    }

    public function setFormat(?string $format): void
    {
        if (null === $format || '' === $format) {
            unset($this->options['format']);

            return;
        }

        $this->options['format'] = $format;
    }

    /**
     * @return array{region: string, anchor: string, order?: int}|null
     */
    public function getPlacement(): ?array
    {
        return $this->options['placement'] ?? null;
    }

    /**
     * @param array{region: string, anchor: string, order?: int}|null $placement
     */
    public function setPlacement(?array $placement): void
    {
        if (null === $placement) {
            unset($this->options['placement']);

            return;
        }

        $this->options['placement'] = $placement;
    }

    public function getVariant(): ?string
    {
        return $this->options['variant'] ?? null;
    }

    public function setVariant(?string $variant): void
    {
        if (null === $variant) {
            unset($this->options['variant']);

            return;
        }

        $this->options['variant'] = $variant;
    }

    public function getColor(): ?string
    {
        return $this->options['color'] ?? null;
    }

    public function setColor(?string $color): void
    {
        if (null === $color || '' === $color) {
            unset($this->options['color']);

            return;
        }

        $this->options['color'] = $color;
    }

    public function getSize(): ?string
    {
        return $this->options['size'] ?? null;
    }

    public function setSize(?string $size): void
    {
        if (null === $size || '' === $size) {
            unset($this->options['size']);

            return;
        }

        $this->options['size'] = $size;
    }

    public function isShowLabel(): ?bool
    {
        return $this->options['showLabel'] ?? null;
    }

    public function setShowLabel(?bool $showLabel): void
    {
        if (null === $showLabel) {
            unset($this->options['showLabel']);

            return;
        }

        $this->options['showLabel'] = $showLabel;
    }

    public function isShowIcon(): ?bool
    {
        return $this->options['showIcon'] ?? null;
    }

    public function setShowIcon(?bool $showIcon): void
    {
        if (null === $showIcon) {
            unset($this->options['showIcon']);

            return;
        }

        $this->options['showIcon'] = $showIcon;
    }

    public function isBooleanIcon(): ?bool
    {
        return $this->options['booleanIcon'] ?? null;
    }

    public function setBooleanIcon(?bool $booleanIcon): void
    {
        if (null === $booleanIcon) {
            unset($this->options['booleanIcon']);

            return;
        }

        $this->options['booleanIcon'] = $booleanIcon;
    }

    public function getEntityDisplay(): ?string
    {
        return $this->options['entityDisplay'] ?? null;
    }

    public function setEntityDisplay(?string $entityDisplay): void
    {
        if (null === $entityDisplay) {
            unset($this->options['entityDisplay']);

            return;
        }

        $this->options['entityDisplay'] = $entityDisplay;
    }

    #[Assert\Callback]
    public function validate(ExecutionContextInterface $context): void
    {
        switch ($this->type) {
            case self::TYPE_ATTR_DEF:
                if (null === $this->getDefinition()) {
                    $context->buildViolation('The definition must be set.')
                        ->atPath('definition')
                        ->addViolation();
                }
                if (!empty($this->getKey())) {
                    $context->buildViolation('The key must not be set for definitions.')
                        ->atPath('key')
                        ->addViolation();
                }
                break;
            case self::TYPE_BUILT_IN:
                if (empty($this->getKey())) {
                    $context->buildViolation('The key must be set and not empty.')
                        ->atPath('key')
                        ->addViolation();
                }
                break;
            case self::TYPE_SPACER:
                if (!empty($this->getKey())) {
                    $context->buildViolation('The key must not be set for spacers.')
                        ->atPath('key')
                        ->addViolation();
                }
                break;
        }

        if (self::SECTION_GRID === $this->section) {
            $placement = $this->getPlacement();
            if (null === $placement) {
                $context->buildViolation('A placement is required for grid items.')
                    ->atPath('placement')
                    ->addViolation();

                return;
            }

            $region = $placement['region'] ?? null;
            $anchor = $placement['anchor'] ?? null;
            if (!in_array($region, self::REGIONS, true)) {
                $context->buildViolation(sprintf('Invalid grid region "%s".', (string) $region))
                    ->atPath('placement')
                    ->addViolation();
            } elseif (!in_array($anchor, self::ANCHORS[$region], true)) {
                $context->buildViolation(sprintf('Invalid anchor "%s" for region "%s".', (string) $anchor, $region))
                    ->atPath('placement')
                    ->addViolation();
            }
        }
    }
}
