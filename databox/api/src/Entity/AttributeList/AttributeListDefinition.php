<?php

declare(strict_types=1);

namespace App\Entity\AttributeList;

use Alchemy\CoreBundle\Entity\AbstractUuidEntity;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Link;
use App\Entity\Core\AttributeDefinition;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity]
#[ApiResource(
    uriTemplate: '/attribute-lists/{id}/definitions',
    operations: [
        new GetCollection(),
    ],
    uriVariables: [
        'id' => new Link(toProperty: 'list', fromClass: AttributeList::class),
    ],
    normalizationContext: [
        'groups' => [
            self::GROUP_LIST,
        ],
    ],
    order: ['position' => 'ASC'],
)]
#[ORM\UniqueConstraint(name: 'list_def_uniq', columns: ['list_id', 'definition_id', 'built_in'])]
class AttributeListDefinition extends AbstractUuidEntity
{
    public const string GROUP_LIST = 'attrlist-def:l';

    #[ORM\ManyToOne(targetEntity: AttributeList::class, inversedBy: 'definitions')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?AttributeList $list = null;

    #[ORM\ManyToOne(targetEntity: AttributeDefinition::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
    #[Groups([self::GROUP_LIST])]
    private ?AttributeDefinition $definition = null;

    #[ORM\Column(type: Types::STRING, length: 30, nullable: true)]
    #[Groups([self::GROUP_LIST])]
    private ?string $builtIn = null;

    #[ORM\Column(type: Types::INTEGER, nullable: false)]
    #[Groups([self::GROUP_LIST])]
    private ?int $position = 0;

    public function getList(): AttributeList
    {
        return $this->list;
    }

    public function setList(AttributeList $list): void
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

    public function getBuiltIn(): ?string
    {
        return $this->builtIn;
    }

    public function setBuiltIn(?string $builtIn): void
    {
        $this->builtIn = $builtIn;
    }

    #[Assert\Callback]
    public function validate(ExecutionContextInterface $context): void
    {
        if (null !== $this->getBuiltIn() && null !== $this->getDefinition()) {
            $context->buildViolation('The definition cannot be set when built-in is set.')
                ->atPath('builtIn')
                ->addViolation();
        } else if (null === $this->getBuiltIn() && null === $this->getDefinition()) {
            $context->buildViolation('Either built-in or definition must be set.')
                ->atPath('builtIn')
                ->addViolation();
        }
    }
}
