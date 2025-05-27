<?php

declare(strict_types=1);

namespace App\Entity\Core;

use Alchemy\CoreBundle\Entity\AbstractUuidEntity;
use Alchemy\CoreBundle\Entity\Traits\CreatedAtTrait;
use Alchemy\CoreBundle\Entity\Traits\UpdatedAtTrait;
use ApiPlatform\Doctrine\Orm\Filter\OrderFilter;
use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use App\Entity\Traits\WorkspaceTrait;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource(
    shortName: 'entity-list',
    operations: [
        new Get(),
        new Delete(security: 'is_granted("DELETE", object)'),
        new Put(security: 'is_granted("EDIT", object)'),
        new Patch(security: 'is_granted("EDIT", object)'),
        new GetCollection(),
        new Post(
            securityPostDenormalize: 'is_granted("CREATE", object)'
        ),
    ],
    normalizationContext: [
        'groups' => [
            self::GROUP_LIST,
        ],
    ],
)]

#[ORM\Entity]
#[ApiFilter(filterClass: SearchFilter::class, strategy: 'exact', properties: [
    'workspace',
    'name',
])]
#[ApiFilter(filterClass: OrderFilter::class, properties: [
    'name',
    'createdAt',
])]
#[ORM\UniqueConstraint(name: 'uniq_ws_type', columns: ['workspace_id', 'name'])]
#[UniqueEntity(
    fields: ['workspace', 'name'],
    message: 'This entity type already exists in the workspace.'
)]
class EntityList extends AbstractUuidEntity implements \Stringable
{
    use CreatedAtTrait;
    use UpdatedAtTrait;
    use WorkspaceTrait;
    public const int TYPE_LENGTH = 100;

    final public const string GROUP_READ = 'entity-list:r';
    final public const string GROUP_LIST = 'entity-list:i';

    #[ORM\Column(type: Types::STRING, length: 100, nullable: false)]
    #[Groups([self::GROUP_LIST, self::GROUP_READ])]
    #[Assert\NotBlank]
    #[Assert\Length(max: self::TYPE_LENGTH)]
    private ?string $name = null;

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): void
    {
        $this->name = $name;
    }

    public function __toString()
    {
        return $this->name ?? $this->getId() ?? '';
    }
}
