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
class AttributeListDefinition extends AbstractUuidEntity
{
    public const string GROUP_LIST = 'attrlist-def:l';

    #[ORM\ManyToOne(targetEntity: AttributeList::class, inversedBy: 'definitions')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?AttributeList $list = null;

    #[ORM\ManyToOne(targetEntity: AttributeDefinition::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Groups([self::GROUP_LIST])]
    private ?AttributeDefinition $definition = null;

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
}
