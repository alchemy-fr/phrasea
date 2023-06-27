<?php

declare(strict_types=1);

namespace App\Entity\Core;

use Alchemy\AclBundle\AclObjectInterface;
use ApiPlatform\Core\Annotation\ApiResource;
use App\Doctrine\Listener\SoftDeleteableInterface;
use App\Entity\AbstractUuidEntity;
use App\Entity\ESIndexableInterface;
use App\Entity\SearchableEntityInterface;
use App\Entity\SearchDeleteDependencyInterface;
use App\Entity\SearchDependencyInterface;
use App\Entity\Traits\CreatedAtTrait;
use App\Entity\Traits\DeletedAtTrait;
use App\Entity\Traits\LocaleTrait;
use App\Entity\Traits\UpdatedAtTrait;
use App\Entity\Traits\WorkspacePrivacyTrait;
use App\Entity\Traits\WorkspaceTrait;
use App\Entity\TranslatableInterface;
use App\Entity\WithOwnerIdInterface;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection as DoctrineCollection;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Serializer\Annotation\MaxDepth;

/**
 * @Gedmo\SoftDeleteable(fieldName="deletedAt", hardDelete=false)
 *
 * @ApiResource()
 */
#[ORM\Table]
#[ORM\UniqueConstraint(name: 'uniq_coll_ws_key', columns: ['workspace_id', 'key'])]
#[ORM\Entity(repositoryClass: \App\Repository\Core\CollectionRepository::class)]
class Collection extends AbstractUuidEntity implements SoftDeleteableInterface, WithOwnerIdInterface, AclObjectInterface, TranslatableInterface, SearchableEntityInterface, SearchDependencyInterface, SearchDeleteDependencyInterface, ESIndexableInterface, \Stringable
{
    use CreatedAtTrait;
    use UpdatedAtTrait;
    use DeletedAtTrait;
    use WorkspaceTrait;
    use LocaleTrait;
    use WorkspacePrivacyTrait;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $title = null;

    #[ORM\Column(type: 'string', length: 36)]
    private ?string $ownerId = null;

    #[ORM\ManyToOne(targetEntity: Collection::class, inversedBy: 'children')]
    #[ORM\JoinColumn(nullable: true)]
    #[MaxDepth(1)]
    private ?self $parent = null;

    /**
     * @var self[]
     */
    #[ORM\OneToMany(targetEntity: Collection::class, mappedBy: 'parent')]
    #[ORM\JoinColumn(nullable: true)]
    #[MaxDepth(1)]
    private ?DoctrineCollection $children = null;

    /**
     * Virtual.
     */
    private ?bool $hasChildren = null;

    #[ORM\OneToMany(targetEntity: CollectionAsset::class, mappedBy: 'collection', cascade: ['persist'])]
    private ?DoctrineCollection $assets = null;

    #[ORM\OneToMany(targetEntity: Asset::class, mappedBy: 'referenceCollection')]
    private ?DoctrineCollection $referenceAssets = null;

    #[ORM\ManyToOne(targetEntity: Workspace::class, inversedBy: 'collections')]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['_'])]
    protected ?Workspace $workspace = null;

    /**
     * Unique key by workspace. Used to prevent duplicates.
     */
    #[ORM\Column(type: 'string', length: 4096, nullable: true)]
    private ?string $key = null;

    public function __construct()
    {
        parent::__construct();
        $this->children = new ArrayCollection();
        $this->assets = new ArrayCollection();
        $this->referenceAssets = new ArrayCollection();
    }

    public function getTitle(): ?string
    {
        if (null !== $this->deletedAt) {
            return sprintf('(being deleted...) %s', $this->title);
        }

        return $this->title;
    }

    public function setTitle(?string $title): void
    {
        $this->title = $title;
    }

    public function getParent(): ?self
    {
        return $this->parent;
    }

    public function setParent(?self $parent): void
    {
        if (null !== $parent && $parent->getWorkspace() !== $this->getWorkspace()) {
            throw new BadRequestHttpException('Cannot add a sub-collection in a different workspace');
        }

        $this->parent = $parent;
    }

    public function getSortName(): string
    {
        return strtolower($this->title ?? '');
    }

    /**
     * @return Collection[]
     */
    public function getChildren(): DoctrineCollection
    {
        return $this->children;
    }

    public function getOwnerId(): ?string
    {
        return $this->ownerId;
    }

    public function setOwnerId(?string $ownerId): void
    {
        $this->ownerId = $ownerId;
    }

    /**
     * @return CollectionAsset[]
     */
    public function getAssets(): DoctrineCollection
    {
        return $this->assets;
    }

    public function getAbsolutePath(): string
    {
        $path = '/'.$this->getId();
        if (null !== $this->parent) {
            return $this->parent->getAbsolutePath().$path;
        }

        return $path;
    }

    public function getPathDepth(): int
    {
        $depth = 0;
        $ptr = $this;
        while (null !== $ptr = $ptr->parent) {
            ++$depth;
        }

        return $depth;
    }

    public function isRoot(): bool
    {
        return null === $this->parent;
    }

    public function getBestPrivacyInParentHierarchy(): int
    {
        $bestPrivacy = $this->privacy;

        // Early return if best
        if (WorkspaceItemPrivacyInterface::PUBLIC === $bestPrivacy) {
            return $this->privacy;
        }

        if (
            null !== $this->parent
            && ($better = $this->parent->getBestPrivacyInParentHierarchy()) > $bestPrivacy
        ) {
            return $better;
        }

        return $bestPrivacy;
    }

    public function getBestPrivacyInDescendantHierarchy(): int
    {
        $bestPrivacy = $this->privacy;
        // Early return if best
        if (WorkspaceItemPrivacyInterface::PUBLIC === $bestPrivacy) {
            return $this->privacy;
        }

        foreach ($this->children as $child) {
            if (($better = $child->getBestPrivacyInParentHierarchy()) > $bestPrivacy) {
                // Early return if best
                if (WorkspaceItemPrivacyInterface::PUBLIC === $bestPrivacy) {
                    return $this->privacy;
                }
                $bestPrivacy = $better;
            }
        }

        return $bestPrivacy;
    }

    public function isVisible(): bool
    {
        return $this->privacy >= WorkspaceItemPrivacyInterface::PRIVATE;
    }

    public function getAbsoluteTitle(): string
    {
        $path = $this->getTitle();
        if (null !== $this->parent) {
            return $this->parent->getAbsoluteTitle().' / '.$path;
        }

        return $path;
    }

    public function getAclOwnerId(): string
    {
        return $this->getOwnerId() ?? '';
    }

    public function __toString(): string
    {
        return $this->getAbsoluteTitle() ?? $this->getId();
    }

    public function getKey(): ?string
    {
        return $this->key;
    }

    public function setKey(?string $key): void
    {
        $this->key = $key;
    }

    public function getHasChildren(): ?bool
    {
        return $this->hasChildren;
    }

    /**
     * @internal
     */
    public function setHasChildren(?bool $hasChildren): void
    {
        $this->hasChildren = $hasChildren;
    }

    public function getSearchDeleteDependencies(): array
    {
        if ($this->parent) {
            return [$this->parent];
        }

        return [];
    }

    public function isObjectIndexable(): bool
    {
        return null === $this->workspace->getDeletedAt();
    }
}
