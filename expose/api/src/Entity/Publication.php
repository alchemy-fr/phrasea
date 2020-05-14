<?php

declare(strict_types=1);

namespace App\Entity;

use Alchemy\AclBundle\AclObjectInterface;
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
 *              "security"="is_granted('EDIT', object)"
 *         },
 *         "delete"={
 *              "security"="is_granted('DELETE', object)"
 *         },
 *     },
 *     collectionOperations={
 *         "get"={
 *              "normalization_context"=Publication::API_LIST,
 *          },
 *         "post"={
 *             "security"="is_granted('publication:create')"
 *         }
 *     }
 * )
 */
class Publication implements AclObjectInterface
{
    const GROUP_INDEX = 'publication:index';
    const GROUP_READ = 'publication:read';
    const GROUP_ADMIN_READ = 'publication:admin:read';
    const GROUP_LIST = 'publication:index';

    const API_READ = [
        'groups' => [self::GROUP_READ],
        'swagger_definition_name' => 'Read',
    ];
    const API_LIST = [
        'groups' => [self::GROUP_LIST],
        'swagger_definition_name' => 'List',
    ];

    const SECURITY_METHOD_NONE = null;
    const SECURITY_METHOD_PASSWORD = 'password';
    const SECURITY_METHOD_AUTHENTICATION = 'authentication';

    /**
     * @ApiProperty(identifier=true)
     * @Groups({"publication:index", "publication:index", "publication:read", "asset:read"})
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
     * @ORM\Column(type="string", length=255)
     * @Groups({"publication:index", "publication:index", "publication:read"})
     */
    private ?string $title = null;

    /**
     * @ApiProperty()
     *
     * @ORM\Column(type="text", nullable=true)
     * @Groups({"publication:index", "publication:read"})
     */
    private ?string $description = null;

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
    private Collection $assets;

    /**
     * @ApiProperty(
     *     attributes={
     *         "swagger_context"={
     *             "$ref"="#/definitions/PublicationProfile",
     *         }
     *     }
     * )
     * @ORM\ManyToOne(targetEntity="PublicationProfile")
     * @Groups({"publication:admin:read"})
     */
    private ?PublicationProfile $profile = null;

    /**
     * @ApiProperty(
     *     attributes={
     *         "swagger_context"={
     *             "$ref"="#/definitions/Asset",
     *         }
     *     }
     * )
     * @ORM\ManyToOne(targetEntity="Asset")
     */
    private ?Asset $package = null;

    /**
     * @ApiProperty()
     * @Groups({"publication:read", "publication:index"})
     */
    private ?string $packageUrl = null;

    /**
     * @ApiProperty()
     * @ORM\Column(type="string", nullable=true)
     * @Groups({"publication:admin:read"})
     */
    private ?string $ownerId = null;

    /**
     * @ApiProperty()
     * @Groups({"publication:index", "publication:read"})
     */
    private bool $authorized = false;

    /**
     * Password identifier for the current publication branch.
     *
     * @ApiProperty()
     * @Groups({"publication:index", "publication:read"})
     */
    private ?string $securityContainerId = null;

    /**
     * @ApiProperty()
     * @Groups({"publication:index"})
     */
    private ?string $authorizationError = null;

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
     * @ORM\ManyToOne(targetEntity="Publication", inversedBy="children")
     */
    private ?Publication $parent = null;

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
     * @ORM\OneToMany(targetEntity="Publication", mappedBy="parent", cascade={"remove"})
     * @ORM\JoinTable(name="publication_children",
     *      joinColumns={@ORM\JoinColumn(name="parent_id", referencedColumnName="id")},
     *      inverseJoinColumns={@ORM\JoinColumn(name="child_id", referencedColumnName="id")}
     * )
     */
    private Collection $children;

    /**
     * @ORM\Embedded(class="App\Entity\PublicationConfig")
     * @Groups({"publication:index", "publication:admin:read"})
     */
    private PublicationConfig $config;

    /**
     * Virtual property.
     *
     * @ApiProperty()
     *
     * @Groups({"publication:write"})
     */
    private ?string $parentId = null;

    /**
     * URL slug.
     *
     * @ApiProperty()
     * @Groups({"publication:index", "publication:index", "publication:read"})
     *
     * @ORM\Column(type="string", length=100, nullable=true, unique=true)
     */
    protected ?string $slug = null;

    /**
     * @ORM\Column(type="datetime")
     * @Groups({"publication:read"})
     */
    private DateTime $createdAt;

    /**
     * @Groups({"publication:admin:read", "publication:index"})
     */
    private ?string $coverUrl = null;

    public function __construct()
    {
        $this->createdAt = new DateTime();
        $this->assets = new ArrayCollection();
        $this->children = new ArrayCollection();
        $this->config = new PublicationConfig();
        $this->id = Uuid::uuid4();
    }

    public function getId(): string
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

    /**
     * @Groups({"publication:read"})
     */
    public function isEnabled(): bool
    {
        return $this->config->isEnabled()
            && (!$this->profile || $this->profile->getConfig()->isEnabled());
    }

    /**
     * @Groups({"publication:read"})
     */
    public function isPubliclyListed(): bool
    {
        return $this->config->isPubliclyListed()
            || ($this->profile && $this->profile->getConfig()->isPubliclyListed());
    }

    /**
     * @Groups({"publication:read"})
     */
    public function getLayout(): string
    {
        return $this->config->getLayout() ?? ($this->profile ? $this->profile->getConfig()->getLayout() : 'gallery');
    }

    /**
     * @Groups({"publication:read"})
     */
    public function getCss(): ?string
    {
        $css = [];
        if ($this->config->getCss()) {
            $css[] = $this->config->getCss();
        }
        if ($this->profile && $this->profile->getConfig()->getCss()) {
            $css[] = $this->profile->getConfig()->getCss();
        }

        if (!empty($css)) {
            return implode("\n", $css);
        }

        return null;
    }

    /**
     * @Groups({"publication:read"})
     */
    public function getUrls(): array
    {
        $urls = $this->config->getUrls();
        if ($this->profile && $this->profile->getConfig()->getCss()) {
            $urls = array_merge($this->profile->getConfig()->getUrls(), $urls);
        }

        return $urls;
    }

    /**
     * @Groups({"publication:read"})
     */
    public function getCopyrightText(): ?string
    {
        return $this->config->getCopyrightText() ?? ($this->profile ? $this->profile->getConfig()->getCopyrightText() : null);
    }

    /**
     * @Groups({"publication:read"})
     */
    public function getTheme(): ?string
    {
        return $this->config->getTheme() ?? ($this->profile ? $this->profile->getConfig()->getTheme() : null);
    }

    /**
     * @Groups({"publication:index", "publication:read"})
     */
    public function getSecurityMethod(): ?string
    {
        return $this->config->getSecurityMethod() ?? ($this->profile ? $this->profile->getConfig()->getSecurityMethod() : null);
    }

    /**
     * @Groups({"publication:admin:read"})
     */
    public function getSecurityOptions(): array
    {
        if (!empty($this->config->getSecurityOptions())) {
            return $this->config->getSecurityOptions();
        }

        if ($this->profile) {
            return $this->profile->getConfig()->getSecurityOptions();
        }

        return [];
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

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): void
    {
        $this->description = $description;
    }

    public function getPackage(): ?Asset
    {
        return $this->package;
    }

    public function setPackage(?Asset $package): void
    {
        $this->package = $package;
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
        return $this->title ?? $this->getId() ?? '';
    }

    public function getSecurityContainer(): self
    {
        if (self::SECURITY_METHOD_NONE !== $this->config->getSecurityMethod()) {
            return $this;
        }

        return $this->parent ?? $this;
    }

    public function isAuthorized(): bool
    {
        return $this->authorized;
    }

    public function setAuthorized(bool $authorized): void
    {
        $this->authorized = $authorized;
    }

    public function getSecurityContainerId(): ?string
    {
        return $this->securityContainerId;
    }

    public function setSecurityContainerId(?string $securityContainerId): void
    {
        $this->securityContainerId = $securityContainerId;
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

    public function getParent(): ?self
    {
        return $this->parent;
    }

    public function setParent(?self $parent): void
    {
        $this->parent = $parent;
    }

    public function addChild(self $child): void
    {
        $child->setParent($this);
        $this->children->add($child);
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
        $this->parentId = $parentId;
    }

    public function getConfig(): PublicationConfig
    {
        return $this->config;
    }

    public function getProfile(): ?PublicationProfile
    {
        return $this->profile;
    }

    public function setProfile(?PublicationProfile $profile): void
    {
        $this->profile = $profile;
    }

    /**
     * @Groups({"publication:read"})
     */
    public function getTerms(): TermsConfig
    {
        if ($this->profile) {
            return $this->config->getTerms()->mergeWithProfile($this->profile->getConfig()->getTerms());
        }

        return $this->config->getTerms();
    }

    public function getCover(): ?Asset
    {
        return $this->config->getCover() ?? ($this->profile ? $this->profile->getConfig()->getCover() : null);
    }

    /**
     * @Groups({"publication:index", "publication:read"})
     */
    public function getCoverUrl(): ?string
    {
        return $this->coverUrl;
    }

    public function setCoverUrl(?string $coverUrl): void
    {
        $this->coverUrl = $coverUrl;
    }

    // @see https://github.com/doctrine/orm/issues/7944
    public function __sleep()
    {
        return [];
    }
}
