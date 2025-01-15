<?php

declare(strict_types=1);

namespace App\Entity\Template;

use Alchemy\AclBundle\AclObjectInterface;
use Alchemy\CoreBundle\Entity\AbstractUuidEntity;
use Alchemy\CoreBundle\Entity\Traits\CreatedAtTrait;
use Alchemy\CoreBundle\Entity\Traits\UpdatedAtTrait;
use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use App\Api\Model\Input\Template\AssetDataTemplateInput;
use App\Api\Model\Output\Template\AssetDataTemplateOutput;
use App\Api\Provider\AssetDataTemplateCollectionProvider;
use App\Entity\Core\Collection;
use App\Entity\Core\Tag;
use App\Entity\Traits\OwnerIdTrait;
use App\Entity\Traits\WorkspaceTrait;
use App\Entity\WithOwnerIdInterface;
use App\Repository\Core\AssetDataTemplateRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection as DoctrineCollection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Table]
#[ORM\Entity(repositoryClass: AssetDataTemplateRepository::class)]
#[ApiResource(
    shortName: 'asset-data-template',
    operations: [
        new Get(
            normalizationContext: [
                'groups' => [
                    AssetDataTemplate::GROUP_LIST,
                    AssetDataTemplate::GROUP_READ,
                ],
            ],
            security: 'is_granted("READ", object)'
        ),
        new Put(
            normalizationContext: [
                'groups' => [
                    AssetDataTemplate::GROUP_LIST,
                    AssetDataTemplate::GROUP_READ,
                ],
            ],
            security: 'is_granted("EDIT", object)',
        ),
        new Delete(security: 'is_granted("DELETE", object)'),
        new GetCollection(),
        new Post(
            securityPostDenormalize: 'is_granted("CREATE", object)',
        ),
    ],
    normalizationContext: [
        'groups' => [AssetDataTemplate::GROUP_LIST],
    ],
    input: AssetDataTemplateInput::class,
    output: AssetDataTemplateOutput::class,
    provider: AssetDataTemplateCollectionProvider::class,
)]
#[ApiFilter(SearchFilter::class, properties: ['workspace' => 'exact'])]
class AssetDataTemplate extends AbstractUuidEntity implements AclObjectInterface, WithOwnerIdInterface, \Stringable
{
    use OwnerIdTrait;
    use CreatedAtTrait;
    use UpdatedAtTrait;
    use WorkspaceTrait;
    final public const string GROUP_READ = 'adt:read';
    final public const string GROUP_LIST = 'adt:index';

    /**
     * Template name.
     */
    #[ORM\Column(type: Types::STRING, length: 255, nullable: false)]
    private ?string $name = null;

    #[ORM\Column(type: Types::BOOLEAN, nullable: false)]
    #[Groups([AssetDataTemplate::GROUP_READ])]
    private bool $public = false;

    /**
     * Asset title.
     */
    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    #[Groups([AssetDataTemplate::GROUP_READ])]
    private ?string $title = null;

    #[ORM\ManyToMany(targetEntity: Tag::class)]
    #[Groups([AssetDataTemplate::GROUP_READ])]
    private ?DoctrineCollection $tags = null;

    /**
     * @var DoctrineCollection<TemplateAttribute>|null
     */
    #[ORM\OneToMany(mappedBy: 'template', targetEntity: TemplateAttribute::class, cascade: ['persist', 'remove'])]
    #[Groups([AssetDataTemplate::GROUP_READ])]
    private ?DoctrineCollection $attributes = null;

    #[ORM\ManyToOne(targetEntity: Collection::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?Collection $collection = null;

    #[ORM\Column(type: Types::BOOLEAN)]
    private bool $includeCollectionChildren = false;

    #[ORM\Column(type: Types::SMALLINT, nullable: true)]
    private ?int $privacy = null;

    #[ORM\Column(type: Types::JSON)]
    private array $data = [];

    public function __construct()
    {
        parent::__construct();
        $this->attributes = new ArrayCollection();
    }

    public function isPublic(): bool
    {
        return $this->public;
    }

    public function setPublic(bool $public): void
    {
        $this->public = $public;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(?string $title): void
    {
        $this->title = $title;
    }

    public function getTags(): ?DoctrineCollection
    {
        return $this->tags;
    }

    public function setTags(?DoctrineCollection $tags): void
    {
        $this->tags = $tags;
    }

    public function getAttributes(): ?DoctrineCollection
    {
        return $this->attributes;
    }

    public function setAttributes(?DoctrineCollection $attributes): void
    {
        $this->attributes = $attributes;
    }

    public function getCollection(): ?Collection
    {
        return $this->collection;
    }

    public function setCollection(?Collection $collection): void
    {
        $this->collection = $collection;
    }

    public function getData(): array
    {
        return $this->data;
    }

    public function setData(array $data): void
    {
        $this->data = $data;
    }

    public function getAclOwnerId(): string
    {
        return $this->getOwnerId() ?? 'anon.';
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): void
    {
        $this->name = $name;
    }

    public function getPrivacy(): ?int
    {
        return $this->privacy;
    }

    public function setPrivacy(?int $privacy): void
    {
        $this->privacy = $privacy;
    }

    public function addAttribute(TemplateAttribute $attribute): void
    {
        $attribute->setTemplate($this);
        $this->attributes->add($attribute);
    }

    public function __toString(): string
    {
        return $this->getName() ?? $this->getId();
    }

    public function isIncludeCollectionChildren(): bool
    {
        return $this->includeCollectionChildren;
    }

    public function getCollectionDepth(): int
    {
        return $this->collection ? $this->collection->getPathDepth() + 1 : 0;
    }

    public function getCollectionId(): ?string
    {
        return $this->collection?->getId();
    }

    public function setIncludeCollectionChildren(bool $includeCollectionChildren): void
    {
        $this->includeCollectionChildren = $includeCollectionChildren;
    }
}
