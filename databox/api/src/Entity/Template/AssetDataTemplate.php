<?php

declare(strict_types=1);

namespace App\Entity\Template;

use Alchemy\AclBundle\AclObjectInterface;
use ApiPlatform\Core\Annotation\ApiFilter;
use App\Entity\AbstractUuidEntity;
use App\Entity\Core\Collection;
use App\Entity\Traits\CreatedAtTrait;
use App\Entity\Traits\UpdatedAtTrait;
use App\Entity\Traits\WorkspaceTrait;
use App\Entity\WithOwnerIdInterface;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection as DoctrineCollection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use ApiPlatform\Core\Bridge\Doctrine\Orm\Filter\SearchFilter;

/**
 * @ORM\Entity()
 * @ORM\Table()
 * @ApiFilter(SearchFilter::class, properties={"workspace"="exact"})
 */
class AssetDataTemplate extends AbstractUuidEntity implements AclObjectInterface, WithOwnerIdInterface
{
    use CreatedAtTrait;
    use UpdatedAtTrait;
    use WorkspaceTrait;

    /**
     * Template name.
     *
     * @ORM\Column(type="string", length=255, nullable=false)
     */
    private ?string $name = null;

    /**
     * @ORM\Column(type="boolean", nullable=false)
     * @Groups({"asset-data-template:read"})
     */
    private bool $public = false;

    /**
     * @ORM\Column(type="string", length=36)
     * @Groups({"asset-data-template:read"})
     */
    private ?string $ownerId = null;

    /**
     * Asset title.
     *
     * @ORM\Column(type="string", length=255, nullable=true)
     * @Groups({"asset-data-template:read"})
     */
    private ?string $title = null;

    /**
     * @ORM\ManyToMany(targetEntity="App\Entity\Core\Tag")
     * @Groups({"asset-data-template:read"})
     */
    private ?DoctrineCollection $tags = null;

    /**
     * @var TemplateAttribute[]
     * @ORM\OneToMany(targetEntity=TemplateAttribute::class, mappedBy="template", cascade={"persist", "remove"})
     * @Groups({"asset-data-template:read"})
     */
    private ?DoctrineCollection $attributes = null;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Core\Collection")
     * @ORM\JoinColumn(nullable=true)
     */
    private ?Collection $collection = null;

    /**
     * @ORM\Column(type="boolean")
     */
    private bool $includeCollectionChildren = false;

    /**
     * @ORM\Column(type="smallint", nullable=true)
     */
    private ?int $privacy = null;

    /**
     * @ORM\Column(type="json")
     */
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

    public function getOwnerId(): ?string
    {
        return $this->ownerId;
    }

    public function setOwnerId(?string $ownerId): void
    {
        $this->ownerId = $ownerId;
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
        return $this->ownerId ?? 'anon.';
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

    public function __toString()
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
        return $this->collection ? $this->collection->getId() : null;
    }

    public function setIncludeCollectionChildren(bool $includeCollectionChildren): void
    {
        $this->includeCollectionChildren = $includeCollectionChildren;
    }
}
