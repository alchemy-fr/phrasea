<?php

declare(strict_types=1);

namespace App\Entity\Template;

use Alchemy\AclBundle\AclObjectInterface;
use App\Entity\AbstractUuidEntity;
use App\Entity\Core\Attribute;
use App\Entity\Core\Collection;
use App\Entity\Core\WorkspaceItemPrivacyInterface;
use App\Entity\Traits\CreatedAtTrait;
use App\Entity\Traits\UpdatedAtTrait;
use App\Entity\Traits\WorkspaceTrait;
use App\Entity\WithOwnerIdInterface;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection as DoctrineCollection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * @ORM\Entity()
 * @ORM\Table()
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
     * @Groups({"data-tpl:index"})
     * @ORM\Column(type="boolean", nullable=false)
     */
    private bool $public = false;

    /**
     * @Groups({"data-tpl:index"})
     * @ORM\Column(type="string", length=36)
     */
    private ?string $ownerId = null;

    /**
     * Asset title.
     *
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private ?string $title = null;

    /**
     * @ORM\ManyToMany(targetEntity="App\Entity\Core\Tag")
     */
    private ?DoctrineCollection $tags = null;

    /**
     * @var TemplateAttribute[]
     * @ORM\OneToMany(targetEntity=TemplateAttribute::class, mappedBy="template", cascade={"persist", "remove"})
     */
    private ?DoctrineCollection $attributes = null;

    /**
     * @Groups({"data-tpl:index"})
     * @ORM\ManyToOne(targetEntity="App\Entity\Core\Collection")
     * @ORM\JoinColumn(nullable=true)
     */
    private ?Collection $collection = null;

    /**
     * @ORM\Column(type="smallint", nullable=true)
     * @Groups({"data-tpl:index"})
     */
    private ?int $privacy = null;

    /**
     * @Groups({"data-tpl:index"})
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
}
