<?php

declare(strict_types=1);

namespace App\Entity\AttributeList;

use Alchemy\CoreBundle\Entity\AbstractUuidEntity;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\Put;
use App\Api\Model\Input\AttributeListItemInput;
use App\Api\Model\Output\AttributeListItemOutput;
use App\Api\Processor\PutAttributeListItemProcessor;
use App\Entity\Core\AttributeDefinition;
use App\Security\Voter\AbstractVoter;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

#[ORM\Entity]
#[ApiResource(
    uriTemplate: '/attribute-lists/{id}/items',
    operations: [
        new GetCollection(),
        new Put(
            uriTemplate: '/attribute-lists/{id}/items/{itemId}',
            security: 'is_granted("'.AbstractVoter::EDIT.'", object.getList())',
            processor: PutAttributeListItemProcessor::class,
        ),
    ],
    uriVariables: [
        'id' => new Link(toProperty: 'list', fromClass: AttributeList::class),
        'itemId' => new Link(fromClass: AttributeListItem::class),
    ],
    normalizationContext: [
        'groups' => [
            AttributeList::GROUP_READ,
        ],
    ],
    input: AttributeListItemInput::class,
    output: AttributeListItemOutput::class,
    order: ['position' => 'ASC'],
)]
#[ORM\UniqueConstraint(name: 'list_def_uniq', columns: ['list_id', 'definition_id', 'key', 'type'])]
class AttributeListItem extends AbstractUuidEntity
{
    final public const int TYPE_ATTR_DEF = 0;
    final public const int TYPE_BUILT_IN = 1;
    final public const int TYPE_DIVIDER = 2;
    final public const int TYPE_SPACER = 3;

    final public const array TYPES = [
        'Attribute Definition' => self::TYPE_ATTR_DEF,
        'Built-in' => self::TYPE_BUILT_IN,
        'Divider' => self::TYPE_DIVIDER,
        'Spacer' => self::TYPE_SPACER,
    ];

    #[ORM\ManyToOne(targetEntity: AttributeList::class, inversedBy: 'items')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?AttributeList $list = null;

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

    public function getList(): ?AttributeList
    {
        return $this->list;
    }

    public function setList(?AttributeList $list): void
    {
        $this->list = $list;
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
        if (null === $format) {
            unset($this->options['format']);

            return;
        }

        $this->options['format'] = $format;
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
    }
}
