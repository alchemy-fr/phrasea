<?php

declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Core\Annotation\ApiProperty;
use ApiPlatform\Core\Annotation\ApiResource;
use App\Controller\GetPublicationAction;
use DateTime;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Uuid;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * @ORM\Entity()
 * @ApiResource(
 *     normalizationContext=Publication::API_READ,
 *     itemOperations={
 *         "get"={
 *              "controller"=GetPublicationAction::class,
 *              "defaults"={
 *                   "_api_receive"=false
 *              },
 *          },
 *         "put"={
 *              "security"="is_granted('publication:publish')"
 *         },
 *         "delete"={
 *              "security"="is_granted('publication:publish')"
 *         },
 *     },
 *     collectionOperations={
 *         "get"={
 *              "normalization_context"=Publication::API_LIST,
 *          },
 *         "post"={
 *             "security"="is_granted('publication:publish')"
 *         }
 *     }
 * )
 */
class Publication
{
    const GROUP_PUB_INDEX = 'publication:index';
    const GROUP_PUB_READ = 'publication:read';
    const GROUP_PUB_ADMIN_READ = 'publication:admin:read';
    const GROUP_PUB_LIST = 'publication:list';

    const API_READ = [
        'groups' => [self::GROUP_PUB_READ],
        'swagger_definition_name' => 'Read',
    ];
    const API_LIST = [
        'groups' => [self::GROUP_PUB_LIST],
        'swagger_definition_name' => 'List',
    ];

    const SECURITY_METHOD_NONE = null;
    const SECURITY_METHOD_PASSWORD = 'password';
    const SECURITY_METHOD_AUTHENTICATION = 'authentication';

    /**
     * @ApiProperty(identifier=true)
     * @Groups({"publication:index", "publication:list", "publication:read", "asset:read"})
     *
     * @var Uuid
     *
     * @ORM\Id
     * @ORM\Column(type="uuid", unique=true)
     */
    private $id;

    /**
     * @ApiProperty()
     *
     * @var string
     *
     * @ORM\Column(type="string", length=255)
     * @Groups({"publication:list", "publication:read"})
     */
    private $title;

    /**
     * @ApiProperty()
     *
     * @var string|null
     *
     * @ORM\Column(type="text", nullable=true)
     * @Groups({"publication:list", "publication:read"})
     */
    private $description;

    /**
     * @var PublicationAsset[]|Collection
     *
     * @ApiProperty(
     *     attributes={
     *         "swagger_context"={
     *             "$ref"="#/definitions/PublicationAsset",
     *         }
     *     }
     * )
     * @Groups({"publication:read"})
     * @ORM\OneToMany(targetEntity="PublicationAsset", mappedBy="publication")
     */
    private $assets;

    /**
     * @var Asset|null
     *
     * @ApiProperty(
     *     attributes={
     *         "swagger_context"={
     *             "$ref"="#/definitions/Asset",
     *         }
     *     }
     * )
     * @ORM\ManyToOne(targetEntity="Asset")
     */
    private $cover;

    /**
     * @var string|null
     *
     * @ApiProperty()
     * @Groups({"publication:read", "publication:list"})
     */
    private $coverUrl;

    /**
     * @var Asset|null
     *
     * @ApiProperty(
     *     attributes={
     *         "swagger_context"={
     *             "$ref"="#/definitions/Asset",
     *         }
     *     }
     * )
     * @ORM\ManyToOne(targetEntity="Asset")
     */
    private $package;

    /**
     * @var string|null
     *
     * @ApiProperty()
     * @Groups({"publication:read", "publication:list"})
     */
    private $packageUrl;

    /**
     * @var bool
     *
     * @ApiProperty()
     * @ORM\Column(type="boolean")
     * @Groups({"publication:read"})
     */
    private $enabled = false;

    /**
     * @var string|null
     *
     * @ApiProperty()
     * @ORM\Column(type="string", nullable=true)
     * @Groups({"publication:admin:read"})
     */
    private $ownerId;

    /**
     * @var bool
     *
     * @ApiProperty()
     * @Groups({"publication:index", "publication:read"})
     */
    private $authorized = false;

    /**
     * @var string|null
     *
     * @ApiProperty()
     * @Groups({"publication:index"})
     */
    private $authorizationError;

    /**
     * @ApiProperty(
     *     attributes={
     *         "swagger_context"={
     *             "$ref"="#/definitions/Publication",
     *         }
     *     }
     * )
     * @Groups({"publication:read"})
     *
     * @var Publication[]|Collection
     *
     * @ORM\ManyToMany(targetEntity="Publication", inversedBy="parents")
     * @ORM\JoinTable(name="publication_children",
     *      joinColumns={@ORM\JoinColumn(name="parent_id", referencedColumnName="id")},
     *      inverseJoinColumns={@ORM\JoinColumn(name="child_id", referencedColumnName="id")}
     * )
     */
    private $children;

    /**
     * @ApiProperty(
     *     attributes={
     *         "swagger_context"={
     *             "$ref"="#/definitions/Publication",
     *         }
     *     }
     * )
     * @Groups({"publication:read"})
     *
     * @var Publication[]|Collection
     *
     * @ORM\ManyToMany(targetEntity="Publication", mappedBy="children")
     */
    private $parents;

    /**
     * Virtual property.
     *
     * @ApiProperty()
     *
     * @var string|null
     * @Groups({"publication:write"})
     */
    private $parentId;

    /**
     * @var bool
     *
     * @ApiProperty()
     * @ORM\Column(type="boolean")
     * @Groups({"publication:read"})
     */
    private $publiclyListed = false;

    /**
     * URL slug.
     *
     * @ApiProperty()
     * @Groups({"publication:index", "publication:list", "publication:read"})
     *
     * @var string|null
     *
     * @ORM\Column(type="string", length=100, nullable=true, unique=true)
     */
    protected $slug;

    /**
     * @var string
     *
     * @ApiProperty()
     * @Groups({"publication:read"})
     * @ORM\Column(type="string", length=20)
     */
    private $layout;

    /**
     * @var string|null
     *
     * @ApiProperty()
     * @Groups({"publication:read"})
     * @ORM\Column(type="string", length=30, nullable=true)
     */
    private $theme;

    /**
     * @var DateTime|null
     *
     * @ApiProperty()
     * @Groups({"publication:read"})
     * @ORM\Column(type="datetime", nullable=true)
     */
    private $beginsAt;

    /**
     * @var DateTime|null
     *
     * @ApiProperty()
     * @Groups({"publication:read"})
     * @ORM\Column(type="datetime", nullable=true)
     */
    private $expiresAt;

    /**
     * @var DateTime
     *
     * @ORM\Column(type="datetime")
     * @Groups({"publication:read"})
     */
    private $createdAt;

    /**
     * "password" or "authentication"
     *
     * @var string|null
     * @ORM\Column(type="string", length=20, nullable=true)
     *
     * @ApiProperty()
     * @Groups({"publication:index", "publication:read"})
     */
    private $securityMethod = self::SECURITY_METHOD_NONE;

    /**
     * If securityMethod="password", you must provide:
     * {"password":"$3cr3t!"}
     *
     * @var array
     * @ORM\Column(type="json_array")
     *
     * @ApiProperty()
     * @Groups({"publication:read"})
     */
    private $securityOptions = [];

    /**
     * @var bool
     * @ORM\Column(type="boolean", nullable=false)
     */
    private $root = true;

    public function __construct()
    {
        $this->createdAt = new DateTime();
        $this->assets = new ArrayCollection();
        $this->children = new ArrayCollection();
        $this->parents = new ArrayCollection();
        $this->id = Uuid::uuid4();
    }

    public function getId()
    {
        return $this->id->__toString();
    }

    /**
     * @return Asset[]
     */
    public function getAssets(): Collection
    {
        return $this->assets;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(string $title): void
    {
        $this->title = $title;
    }

    public function getCreatedAt(): DateTime
    {
        return $this->createdAt;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function setEnabled(bool $enabled): void
    {
        $this->enabled = $enabled;
    }

    public function addAsset(Asset $asset): void
    {
        $asset->getPublications()->add($this);
        $this->assets->add($asset);
    }

    public function removeAsset(Asset $asset): void
    {
        $asset->getPublications()->removeElement($this);
        $this->assets->removeElement($asset);
    }

    public function getLayout(): ?string
    {
        return $this->layout;
    }

    public function setLayout(string $layout): void
    {
        $this->layout = $layout;
    }

    public function getBeginsAt(): ?DateTime
    {
        return $this->beginsAt;
    }

    public function setBeginsAt(?DateTime $beginsAt): void
    {
        $this->beginsAt = $beginsAt;
    }

    public function getExpiresAt(): ?DateTime
    {
        return $this->expiresAt;
    }

    public function setExpiresAt(?DateTime $expiresAt): void
    {
        $this->expiresAt = $expiresAt;
    }

    public function getTheme(): ?string
    {
        return $this->theme;
    }

    public function setTheme(?string $theme): void
    {
        $this->theme = $theme;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): void
    {
        $this->description = $description;
    }

    public function isPubliclyListed(): bool
    {
        return $this->publiclyListed;
    }

    public function setPubliclyListed(bool $publiclyListed): void
    {
        $this->publiclyListed = $publiclyListed;
    }

    public function getCover(): ?Asset
    {
        return $this->cover;
    }

    public function setCover(?Asset $cover): void
    {
        $this->cover = $cover;
    }

    public function getPackage(): ?Asset
    {
        return $this->package;
    }

    public function setPackage(?Asset $package): void
    {
        $this->package = $package;
    }

    public function getCoverUrl(): ?string
    {
        return $this->coverUrl;
    }

    public function setCoverUrl(?string $coverUrl): void
    {
        $this->coverUrl = $coverUrl;
    }

    public function getPackageUrl(): ?string
    {
        return $this->packageUrl;
    }

    public function setPackageUrl(?string $packageUrl): void
    {
        $this->packageUrl = $packageUrl;
    }

    public function getSlug(): ?string
    {
        return $this->slug;
    }

    public function setSlug(?string $slug): void
    {
        $this->slug = $slug;
    }

    public function __toString()
    {
        return $this->getId().($this->title ? '-'.$this->title : '');
    }

    public function getSecurityMethod(): ?string
    {
        return $this->securityMethod;
    }

    public function setSecurityMethod(?string $securityMethod): void
    {
        $this->securityMethod = $securityMethod;
    }

    public function getSecurityOptions(): array
    {
        return $this->securityOptions;
    }

    public function setSecurityOptions(array $securityOptions): void
    {
        $this->securityOptions = $securityOptions;
    }

    public function isAuthorized(): bool
    {
        return $this->authorized;
    }

    public function setAuthorized(bool $authorized): void
    {
        $this->authorized = $authorized;
    }

    public function getAuthorizationError(): ?string
    {
        return $this->authorizationError;
    }

    public function setAuthorizationError(?string $authorizationError): void
    {
        $this->authorizationError = $authorizationError;
    }

    /**
     * @return Publication[]
     */
    public function getChildren(): Collection
    {
        return $this->children;
    }

    public function setChildren(Collection $children): void
    {
        $this->children = $children;
    }

    /**
     * @return Publication[]
     */
    public function getParents(): Collection
    {
        return $this->children;
    }

    public function addChild(self $child): void
    {
        $child->getParents()->add($this);
        $this->children->add($child);
    }

    public function addParent(self $parent): void
    {
        $parent->getChildren()->add($this);
        $this->parents->add($parent);
    }

    public function isRoot(): bool
    {
        return $this->root;
    }

    public function setRoot(bool $root): void
    {
        $this->root = $root;
    }

    public function getOwnerId(): ?string
    {
        return $this->ownerId;
    }

    public function setOwnerId(?string $ownerId): void
    {
        $this->ownerId = $ownerId;
    }

    public function getParentId(): ?string
    {
        return $this->parentId;
    }

    public function setParentId(?string $parentId): void
    {
        $this->setRoot(false);
        $this->parentId = $parentId;
    }
}
