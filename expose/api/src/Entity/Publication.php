<?php

declare(strict_types=1);

namespace App\Entity;

use Alchemy\AclBundle\AclObjectInterface;
use ApiPlatform\Core\Annotation\ApiFilter;
use ApiPlatform\Core\Annotation\ApiProperty;
use ApiPlatform\Core\Annotation\ApiResource;
use ApiPlatform\Core\Annotation\ApiSubresource;
use App\Controller\GetPublicationAction;
use App\Controller\SortAssetsAction;
use App\Model\LayoutOptions;
use App\Model\MapOptions;
use DateTime;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Uuid;
use Symfony\Component\Serializer\Annotation\Groups;
use ApiPlatform\Core\Bridge\Doctrine\Orm\Filter\OrderFilter;
use App\Filter\PublicationFilter;
use App\Controller\DownloadViaZippyAction;

/**
 * @ORM\Entity()
 * @ApiFilter(OrderFilter::class, properties={"title": "ASC", "createdAt": "DESC", "updatedAt": "DESC"}, arguments={"orderParameterName"="order"})
 * @ApiFilter(PublicationFilter::class, properties={"flatten"})
 * @ApiResource(
 *     attributes={"order"={"title": "ASC"}},
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
 *         "sort_assets"={
 *             "defaults"={
 *                  "_api_receive"=false,
 *                  "_api_respond"=true,
 *             },
 *             "path"="/publications/{id}/sort-assets",
 *             "controller"=SortAssetsAction::class,
 *             "method"="POST",
 *         },
 *     },
 *     collectionOperations={
 *         "get"={
 *              "normalization_context"=Publication::API_LIST,
 *          },
 *         "post"={
 *             "security"="is_granted('publication:create')"
 *         }
 *     },
 *     subresourceOperations={
 *         "api_publication_assets_get_subresource"={
 *             "method"="GET",
 *             "normalization_context"={"groups"={"foobar"}}
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
     * @ApiSubresource(maxDepth=1)
     * @ApiProperty(
     *     attributes={
     *         "swagger_context"={
     *             "$ref"="#/definitions/PublicationAsset",
     *         }
     *     }
     * )
     * @Groups({"publication:read"})
     * @ORM\OneToMany(targetEntity="PublicationAsset", mappedBy="publication", cascade={"remove"})
     * @ORM\OrderBy({"position"="ASC", "createdAt"="ASC"})
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
     * @ORM\JoinColumn(onDelete="SET NULL")
     */
    private ?Asset $package = null;

    /**
     * @ApiProperty(
     *     attributes={
     *         "swagger_context"={
     *             "$ref"="#/definitions/Asset",
     *         }
     *     }
     * )
     * @ORM\ManyToOne(targetEntity="Asset")
     * @ORM\JoinColumn(onDelete="SET NULL")
     * @Groups({"publication:admin:read", "publication:index", "publication:read"})
     */
    private ?Asset $cover = null;

    /**
     * @ApiProperty()
     * @Groups({"publication:read", "publication:index"})
     */
    private ?string $packageUrl = null;

    /**
     * @ApiProperty()
     * @Groups({"publication:read", "publication:index"})
     */
    private ?string $archiveDownloadUrl = null;

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
     *     readableLink=true,
     *     attributes={
     *         "swagger_context"={
     *             "$ref"="#/definitions/Publication",
     *         }
     *     }
     * )
     * @Groups({"publication:read"})
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
     * @ORM\OrderBy({"title"="ASC"})
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
     * @ORM\Column(type="datetime", nullable=true)
     * @Groups({"publication:read", "publication:index"})
     */
    private ?DateTime $date = null;

    /**
     * @ORM\Column(type="datetime")
     * @Groups({"publication:read"})
     */
    private DateTime $createdAt;

    /**
     * @ApiProperty(writable=false)
     * @Groups({"publication:read"})
     */
    private ?string $cssLink = null;

    /**
     * @ORM\Column(type="string", length=36, nullable=true)
     */
    private ?string $zippyId = null;

    /**
     * @ORM\Column(type="string", length=32, nullable=true)
     */
    private ?string $zippyHash = null;

    public function __construct()
    {
        $this->createdAt = new DateTime();
        $this->assets = new ArrayCollection();
        $this->children = new ArrayCollection();
        $this->config = new PublicationConfig();
        $this->config->applyDefaults();
        $this->id = Uuid::uuid4();
    }

    public function getId(): string
    {
        return $this->id->__toString();
    }

    /**
     * @return PublicationAsset[]
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
     * @Groups({"publication:admin:read"})
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

    public function getCssLink(): ?string
    {
        return $this->cssLink;
    }

    public function setCssLink(?string $cssLink): void
    {
        $this->cssLink = $cssLink;
    }

    /**
     * @return Url[]|array
     * @Groups({"publication:read"})
     */
    public function getUrls(): array
    {
        $urls = $this->config->getUrls();
        if ($this->profile && !empty($this->profile->getConfig()->getUrls())) {
            $urls = array_merge($this->profile->getConfig()->getUrls(), $urls);
        }

        return Url::mapUrls($urls);
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

    public function getArchiveDownloadUrl(): ?string
    {
        return $this->archiveDownloadUrl;
    }

    public function setArchiveDownloadUrl(?string $archiveDownloadUrl): void
    {
        $this->archiveDownloadUrl = $archiveDownloadUrl;
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
        if (self::SECURITY_METHOD_NONE !== $this->getSecurityMethod()) {
            return $this;
        }

        return $this->parent ? $this->parent->getSecurityContainer() : $this;
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

    public function setConfig(PublicationConfig $config): void
    {
        $this->config = $this->config->mergeWith($config);
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
            return $this->profile->getConfig()->getTerms()->mergeWith($this->config->getTerms());
        }

        return $this->config->getTerms();
    }

    /**
     * @Groups({"publication:read"})
     */
    public function getDownloadTerms(): TermsConfig
    {
        if ($this->profile) {
            return $this->profile->getConfig()->getDownloadTerms()->mergeWith($this->config->getDownloadTerms());
        }

        return $this->config->getDownloadTerms();
    }

    /**
     * @Groups({"publication:read"})
     */
    public function isDownloadViaEmail(): bool
    {
        if (null !== $this->config->getDownloadViaEmail()) {
            return $this->config->getDownloadViaEmail();
        }

        if ($this->profile) {
            return $this->profile->getConfig()->getDownloadViaEmail() ?? false;
        }

        return false;
    }

    /**
     * @Groups({"publication:read"})
     */
    public function isIncludeDownloadTermsInZippy(): bool
    {
        if (null !== $this->config->getIncludeDownloadTermsInZippy()) {
            return $this->config->getIncludeDownloadTermsInZippy();
        }

        if ($this->profile) {
            return $this->profile->getConfig()->getIncludeDownloadTermsInZippy() ?? false;
        }

        return false;
    }

    public function getCover(): ?Asset
    {
        return $this->cover;
    }

    public function setCover(?Asset $cover): void
    {
        $this->cover = $cover;
    }

    public function getBeginsAt(): ?DateTime
    {
        if (null !== $this->config->getBeginsAt()) {
            return $this->config->getBeginsAt();
        }

        if ($this->profile) {
            return $this->profile->getConfig()->getBeginsAt();
        }

        return null;
    }

    public function getExpiresAt(): ?DateTime
    {
        if (null !== $this->config->getExpiresAt()) {
            return $this->config->getExpiresAt();
        }

        if ($this->profile) {
            return $this->profile->getConfig()->getExpiresAt();
        }

        return null;
    }

    public function getDate(): ?DateTime
    {
        return $this->date;
    }

    public function setDate(?DateTime $date): void
    {
        $this->date = $date;
    }

    /**
     * @Groups({"publication:read"})
     */
    public function getMapOptions(): MapOptions
    {
        if ($this->profile) {
            return $this->profile->getConfig()->getMapOptions()->mergeWith($this->config->getMapOptions());
        }

        return $this->config->getMapOptions();
    }

    /**
     * @Groups({"publication:read"})
     */
    public function getLayoutOptions(): LayoutOptions
    {
        if ($this->profile) {
            return $this->profile->getConfig()->getLayoutOptions()->mergeWith($this->config->getLayoutOptions());
        }

        return $this->config->getLayoutOptions();
    }

    public function getAclOwnerId(): string
    {
        return $this->getOwnerId() ?? '';
    }

    public function getZippyId(): ?string
    {
        return $this->zippyId;
    }

    public function setZippyId(?string $zippyId): void
    {
        $this->zippyId = $zippyId;
    }

    public function getZippyHash(): ?string
    {
        return $this->zippyHash;
    }

    public function setZippyHash(?string $zippyHash): void
    {
        $this->zippyHash = $zippyHash;
    }

    /**
     * @see https://github.com/doctrine/orm/issues/7944
     */
    public function __sleep()
    {
        return [];
    }
}
