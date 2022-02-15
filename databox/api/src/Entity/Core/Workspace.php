<?php

declare(strict_types=1);

namespace App\Entity\Core;

use Alchemy\AclBundle\AclObjectInterface;
use ApiPlatform\Core\Annotation\ApiProperty;
use App\Entity\AbstractUuidEntity;
use App\Entity\Traits\CreatedAtTrait;
use App\Entity\Traits\UpdatedAtTrait;
use App\Entity\WithOwnerIdInterface;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\Collection as DoctrineCollection;

/**
 * @ORM\Entity(repositoryClass="App\Repository\Core\WorkspaceRepository")
 */
class Workspace extends AbstractUuidEntity implements AclObjectInterface, WithOwnerIdInterface
{
    use CreatedAtTrait;
    use UpdatedAtTrait;

    /**
     * @ORM\Column(type="string", length=255, nullable=false)
     */
    private ?string $name = null;

    /**
     * @ORM\Column(type="string", length=50, nullable=false)
     */
    private ?string $slug = null;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private ?string $ownerId = null;

    /**
     * @ORM\Column(type="json", nullable=true)
     */
    private ?array $config = [];

    /**
     * @ORM\Column(type="json", nullable=false)
     */
    private array $enabledLocales = [];

    /**
     * @var Collection[]
     * @ORM\OneToMany(targetEntity="App\Entity\Core\Collection", mappedBy="workspace", cascade={"remove"})
     */
    protected ?DoctrineCollection $collections = null;

    /**
     * @var Tag[]
     * @ORM\OneToMany(targetEntity="App\Entity\Core\Tag", mappedBy="workspace", cascade={"remove"})
     */
    protected ?DoctrineCollection $tags = null;

    /**
     * @var RenditionClass[]
     * @ORM\OneToMany(targetEntity="App\Entity\Core\RenditionClass", mappedBy="workspace", cascade={"remove"})
     */
    protected ?DoctrineCollection $renditionClasses = null;

    /**
     * @var RenditionDefinition[]
     * @ORM\OneToMany(targetEntity="App\Entity\Core\RenditionDefinition", mappedBy="workspace", cascade={"remove"})
     */
    protected ?DoctrineCollection $renditionDefinitions = null;

    /**
     * @var AttributeDefinition[]
     * @ORM\OneToMany(targetEntity="App\Entity\Core\AttributeDefinition", mappedBy="workspace", cascade={"remove"})
     */
    protected ?DoctrineCollection $attributeDefinitions = null;

    /**
     * @var File[]
     * @ORM\OneToMany(targetEntity="App\Entity\Core\File", mappedBy="workspace", cascade={"remove"})
     */
    protected ?DoctrineCollection $files = null;

    public function __construct()
    {
        parent::__construct();
        $this->collections = new ArrayCollection();
        $this->tags = new ArrayCollection();
        $this->renditionClasses = new ArrayCollection();
        $this->renditionDefinitions = new ArrayCollection();
        $this->attributeDefinitions = new ArrayCollection();
        $this->files = new ArrayCollection();
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getCollections(): DoctrineCollection
    {
        return $this->collections;
    }

    public function __toString()
    {
        return $this->getName();
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
     * @ApiProperty(readable=false, writable=false)
     */
    public function getAclOwnerId(): string
    {
        return $this->getOwnerId() ?? '';
    }

    public function getConfig(): ?array
    {
        return $this->config;
    }

    public function setConfig(?array $config): void
    {
        $this->config = $config;
    }

    public function setPhraseanetDataboxId($databoxId): void
    {
        if (null == $this->config) {
            $this->config = [];
        }

        if (empty($databoxId)) {
            unset($this->config['phraseanetDataboxId']);
        } else {
            $this->config['phraseanetDataboxId'] = (int) $databoxId;
        }
    }

    public function getPhraseanetDataboxId(): ?int
    {
        return ($this->config ?? [])['phraseanetDataboxId'] ?? null;
    }

    public function getEnabledLocales(): array
    {
        return $this->enabledLocales;
    }

    public function setEnabledLocales(array $enabledLocales): void
    {
        $this->enabledLocales = $enabledLocales;
    }

    public function getSlug(): ?string
    {
        return $this->slug;
    }

    public function setSlug(?string $slug): void
    {
        $this->slug = $slug;
    }
}
