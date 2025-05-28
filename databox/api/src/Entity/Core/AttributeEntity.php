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
use App\Api\Model\Output\ResolveEntitiesOutput;
use App\Api\Provider\AttributeEntityCollectionProvider;
use App\Entity\Traits\WorkspaceTrait;
use App\Repository\Core\AttributeEntityRepository;
use App\Validator\SameWorkspaceConstraint;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource(
    shortName: 'attribute-entity',
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
    provider: AttributeEntityCollectionProvider::class,
)]

#[ORM\Entity(repositoryClass: AttributeEntityRepository::class)]
#[ApiFilter(filterClass: SearchFilter::class, strategy: 'exact', properties: [
    'workspace',
    'list',
])]
#[ApiFilter(filterClass: OrderFilter::class, properties: [
    'value',
    'createdAt',
    'position',
])]
#[ORM\Index(columns: ['list_id'], name: 'entity_list_idx')]
#[SameWorkspaceConstraint(
    properties: ['workspace', 'list.workspace'],
)]
class AttributeEntity extends AbstractUuidEntity
{
    use CreatedAtTrait;
    use UpdatedAtTrait;
    use WorkspaceTrait;

    final public const string GROUP_READ = 'attr-ent:r';
    final public const string GROUP_LIST = 'attr-ent:i';

    #[ORM\ManyToOne(targetEntity: EntityList::class, inversedBy: 'entities')]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull]
    private ?EntityList $list = null;

    #[ORM\Column(type: Types::TEXT, nullable: false)]
    #[Groups([
        self::GROUP_LIST, self::GROUP_READ,
        ResolveEntitiesOutput::GROUP_READ,
    ])]
    #[Assert\NotBlank]
    private ?string $value = null;

    #[ORM\Column(type: Types::INTEGER, nullable: false)]
    private int $position = 0;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    #[Groups([self::GROUP_LIST, self::GROUP_READ])]
    private ?array $translations = null;

    public function getValue(): ?string
    {
        return $this->value;
    }

    public function setValue(?string $value): void
    {
        $this->value = $value;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function setPosition(int $position): void
    {
        $this->position = $position;
    }

    public function getTranslations(): ?array
    {
        return $this->translations;
    }

    public function setTranslations(?array $translations): void
    {
        $this->translations = $translations;
    }

    public function getList(): ?EntityList
    {
        return $this->list;
    }

    /**
     * Used by ES.
     */
    public function getListId(): ?string
    {
        return $this->list?->getId();
    }

    public function setList(?EntityList $list): void
    {
        if (null !== $list && null === $this->workspace) {
            $this->setWorkspace($list->getWorkspace());
        }

        $this->list = $list;
    }

    public function __toString(): string
    {
        return $this->value ?? $this->getId() ?? '';
    }
}
