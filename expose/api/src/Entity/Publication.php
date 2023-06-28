<?php

declare(strict_types=1);

namespace App\Entity;

use Alchemy\AclBundle\AclObjectInterface;
use ApiPlatform\Core\Annotation\ApiFilter;
use ApiPlatform\Core\Annotation\ApiProperty;
use ApiPlatform\Core\Annotation\ApiResource;
use ApiPlatform\Core\Annotation\ApiSubresource;
use ApiPlatform\Core\Bridge\Doctrine\Orm\Filter\DateFilter;
use ApiPlatform\Core\Bridge\Doctrine\Orm\Filter\OrderFilter;
use App\Controller\GetPublicationAction;
use App\Controller\GetPublicationSlugAvailabilityAction;
use App\Controller\SortAssetsAction;
use App\Entity\Traits\CapabilitiesTrait;
use App\Entity\Traits\ClientAnnotationsTrait;
use App\Filter\PublicationFilter;
use App\Model\LayoutOptions;
use App\Model\MapOptions;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Serializer\Annotation\MaxDepth;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

#[ORM\Entity]
#[ApiFilter(OrderFilter::class, properties: ['title' => 'ASC', 'createdAt' => 'DESC', 'updatedAt' => 'DESC'], arguments: ['orderParameterName' => 'order'])]
#[ApiFilter(PublicationFilter::class, properties: ['flatten', 'parentId', 'profileId', 'mine', 'expired'])]
#[ApiFilter(DateFilter::class, properties: ['config.beginsAt', 'config.expiresAt', 'createdAt'])]
#[ApiResource(attributes: ['order' => ['title' => 'ASC'], 'denormalization_context' => ['deep_object_to_populate' => true]], normalizationContext: Publication::API_READ, itemOperations: ['get' => ['controller' => GetPublicationAction::class, 'defaults' => ['_api_receive' => false]], 'put' => ['security' => "is_granted('EDIT', object)"], 'delete' => ['security' => "is_granted('DELETE', object)"], 'sort_assets' => ['defaults' => ['_api_receive' => false, '_api_respond' => true], 'path' => '/publications/{id}/sort-assets', 'controller' => SortAssetsAction::class, 'method' => 'POST']], collectionOperations: ['get' => ['normalization_context' => Publication::API_LIST], 'post' => ['security_post_denormalize' => "is_granted('CREATE', object)"], 'slug_availability' => ['openapi_context' => ['summary' => 'Check whether a slug is available or not.', 'description' => 'Check whether a slug is available or not.', 'responses' => ['200' => ['description' => 'OK', 'content' => ['application/json' => ['schema' => ['type' => 'boolean']]]]], 'parameters' => [['in' => 'path', 'name' => 'slug', 'type' => 'string', 'required' => true, 'description' => 'The slug to verify']]], 'controller' => GetPublicationSlugAvailabilityAction::class, 'path' => '/publications/slug-availability/{slug}', 'pagination_enabled' => false, 'filters' => [], 'defaults' => ['_api_receive' => false, 'input' => false, 'output' => false]]])]
class Publication implements AclObjectInterface, \Stringable
{
    use CapabilitiesTrait;
    use ClientAnnotationsTrait;

    final public const GROUP_READ = 'publication:read';
    final public const GROUP_ADMIN_READ = 'publication:admin:read';
    final public const GROUP_LIST = 'publication:index';

    final public const API_READ = [
        'groups' => [self::GROUP_READ],
        'swagger_definition_name' => 'Read',
    ];
    final public const API_LIST = [
        'groups' => [self::GROUP_LIST],
        'swagger_definition_name' => 'List',
    ];

    final public const SECURITY_METHOD_NONE = null;
    final public const SECURITY_METHOD_PASSWORD = 'password';
    final public const SECURITY_METHOD_AUTHENTICATION = 'authentication';

    /**
     * @var Uuid
     */
    #[Groups(['_', 'publication:index', 'publication:read', 'asset:read'])]
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[ApiProperty(identifier: true)]
    private UuidInterface $id;

    #[ORM\Column(type: 'string', length: 255)]
    #[Groups(['publication:index', 'publication:read'])]
    #[ApiProperty]
    private ?string $title = null;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['publication:index', 'publication:read'])]
    #[ApiProperty]
    private ?string $description = null;

    /**
     * @var Asset[]|Collection
     *
     * @ApiSubresource(maxDepth=1)
     */
    #[Groups(['publication:read'])]
    #[MaxDepth(1)]
    #[ORM\OneToMany(targetEntity: Asset::class, mappedBy: 'publication', cascade: ['remove'])]
    #[ORM\OrderBy(['position' => 'ASC', 'createdAt' => 'ASC'])]
    #[ApiProperty(attributes: ['swagger_context' => ['$ref' => '#/definitions/Asset']])]
    private Collection $assets;

    #[ORM\ManyToOne(targetEntity: PublicationProfile::class)]
    #[Groups(['publication:read', 'publication:admin:read'])]
    #[ApiProperty(attributes: ['swagger_context' => ['$ref' => '#/definitions/PublicationProfile']])]
    private ?PublicationProfile $profile = null;

    #[ORM\ManyToOne(targetEntity: Asset::class)]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    #[ApiProperty(attributes: ['swagger_context' => ['$ref' => '#/definitions/Asset']])]
    private ?Asset $package = null;

    #[ORM\ManyToOne(targetEntity: Asset::class)]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    #[Groups(['publication:admin:read', 'publication:index', 'publication:read'])]
    #[ApiProperty(attributes: ['swagger_context' => ['$ref' => '#/definitions/Asset']])]
    private ?Asset $cover = null;

    #[Groups(['publication:read'])]
    #[ApiProperty]
    private ?string $packageUrl = null;

    #[Groups(['publication:read'])]
    #[ApiProperty]
    private ?string $archiveDownloadUrl = null;

    #[ORM\Column(type: 'string', nullable: true)]
    #[Groups(['publication:admin:read'])]
    #[ApiProperty]
    private ?string $ownerId = null;

    #[Groups(['_', 'publication:index', 'publication:read', 'asset:read'])]
    #[ApiProperty]
    private bool $authorized = false;

    /**
     * Password identifier for the current publication branch.
     */
    #[Groups(['_', 'publication:index', 'publication:read', 'asset:read'])]
    #[ApiProperty]
    private ?string $securityContainerId = null;

    #[Groups(['_', 'publication:index', 'asset:read'])]
    #[ApiProperty]
    private ?string $authorizationError = null;

    #[Groups(['publication:read'])]
    #[MaxDepth(1)]
    #[ORM\ManyToOne(targetEntity: Publication::class, inversedBy: 'children')]
    #[ApiProperty(readableLink: true, attributes: ['swagger_context' => ['$ref' => '#/definitions/Publication']])]
    private ?Publication $parent = null;

    /**
     * @var Publication[]|Collection
     */
    #[ORM\JoinTable(name: 'publication_children')]
    #[ORM\JoinColumn(name: 'parent_id', referencedColumnName: 'id')]
    #[ORM\InverseJoinColumn(name: 'child_id', referencedColumnName: 'id')]
    #[Groups(['publication:read'])]
    #[MaxDepth(1)]
    #[ORM\OneToMany(targetEntity: Publication::class, mappedBy: 'parent', cascade: ['remove'])]
    #[ORM\OrderBy(['title' => 'ASC'])]
    #[ApiProperty(attributes: ['swagger_context' => ['$ref' => '#/definitions/Publication']])]
    private Collection $children;

    #[ORM\Embedded(class: \App\Entity\PublicationConfig::class)]
    #[Groups(['publication:index', 'publication:admin:read'])]
    private PublicationConfig $config;

    /**
     * Virtual property.
     *
     * @deprecated
     */
    #[Groups(['publication:write'])]
    #[ApiProperty]
    private ?string $parentId = null;

    /**
     * URL slug.
     */
    #[Groups(['_', 'publication:index', 'publication:index', 'publication:read'])]
    #[ORM\Column(type: 'string', length: 100, nullable: true, unique: true)]
    #[ApiProperty]
    protected ?string $slug = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    #[Groups(['publication:read', 'publication:index'])]
    private ?\DateTime $date = null;

    #[ORM\Column(type: 'datetime')]
    #[Groups(['publication:read'])]
    private \DateTime $createdAt;

    #[Groups(['publication:read', 'asset:read'])]
    #[ApiProperty(writable: false)]
    private ?string $cssLink = null;

    #[ORM\Column(type: 'string', length: 36, nullable: true)]
    private ?string $zippyId = null;

    #[ORM\Column(type: 'string', length: 32, nullable: true)]
    private ?string $zippyHash = null;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
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

    public function getCreatedAt(): \DateTime
    {
        return $this->createdAt;
    }

    #[Groups(['publication:read', 'publication:index'])]
    public function isEnabled(): bool
    {
        if ($this->profile && null === $this->config->isEnabled()) {
            return true === $this->profile->getConfig()->isEnabled();
        }

        return true === $this->config->isEnabled();
    }

    #[Groups(['publication:read', 'publication:index'])]
    public function isPubliclyListed(): bool
    {
        if ($this->profile && null === $this->config->isPubliclyListed()) {
            return true === $this->profile->getConfig()->isPubliclyListed();
        }

        return true === $this->config->isPubliclyListed();
    }

    #[Groups(['publication:read'])]
    public function getLayout(): string
    {
        return $this->config->getLayout() ?? ($this->profile && $this->profile->getConfig()->getLayout() ? $this->profile->getConfig()->getLayout() : 'gallery');
    }

    #[Groups(['publication:admin:read'])]
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
     */
    #[Groups(['publication:read'])]
    public function getUrls(): array
    {
        $urls = $this->config->getUrls();
        if ($this->profile && !empty($this->profile->getConfig()->getUrls())) {
            $urls = array_merge($this->profile->getConfig()->getUrls(), $urls);
        }

        return Url::mapUrls($urls);
    }

    #[Groups(['publication:read'])]
    public function getCopyrightText(): ?string
    {
        return $this->config->getCopyrightText() ?? ($this->profile ? $this->profile->getConfig()->getCopyrightText() : null);
    }

    #[Groups(['publication:read', 'asset:read'])]
    public function getTheme(): ?string
    {
        return $this->config->getTheme() ?? ($this->profile ? $this->profile->getConfig()->getTheme() : null);
    }

    #[Groups(['_', 'publication:index', 'publication:read', 'asset:read'])]
    public function getSecurityMethod(): ?string
    {
        return $this->config->getSecurityMethod() ?? ($this->profile ? $this->profile->getConfig()->getSecurityMethod() : null);
    }

    #[Groups(['publication:admin:read'])]
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

    public function __toString(): string
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

    #[Groups(['publication:read', 'publication:index'])]
    public function getChildrenCount(): int
    {
        return $this->children->count();
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
        $this->config = $config;
    }

    public function getProfile(): ?PublicationProfile
    {
        return $this->profile;
    }

    public function setProfile(?PublicationProfile $profile): void
    {
        $this->profile = $profile;
    }

    #[Groups(['publication:read'])]
    public function getTerms(): TermsConfig
    {
        if ($this->profile) {
            return $this->profile->getConfig()->getTerms()->mergeWith($this->config->getTerms());
        }

        return $this->config->getTerms();
    }

    #[Groups(['publication:read'])]
    public function getDownloadTerms(): TermsConfig
    {
        if ($this->profile) {
            return $this->profile->getConfig()->getDownloadTerms()->mergeWith($this->config->getDownloadTerms());
        }

        return $this->config->getDownloadTerms();
    }

    #[Groups(['publication:read'])]
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

    #[Groups(['publication:read'])]
    public function isDownloadEnabled(): bool
    {
        if (null !== $this->config->getDownloadEnabled()) {
            return $this->config->getDownloadEnabled();
        }

        if ($this->profile) {
            return $this->profile->getConfig()->getDownloadEnabled() ?? false;
        }

        return false;
    }

    #[Groups(['publication:read'])]
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

    public function isVisible(\DateTimeInterface $now = null): bool
    {
        $now ??= new \DateTime();

        return $this->isEnabled()
            && (null === $this->getBeginsAt() || $this->getBeginsAt() < $now)
            && (null === $this->getExpiresAt() || $this->getExpiresAt() > $now);
    }

    public function getBeginsAt(): ?\DateTime
    {
        if (null !== $this->config->getBeginsAt()) {
            return $this->config->getBeginsAt();
        }

        if ($this->profile) {
            return $this->profile->getConfig()->getBeginsAt();
        }

        return null;
    }

    public function getExpiresAt(): ?\DateTime
    {
        if (null !== $this->config->getExpiresAt()) {
            return $this->config->getExpiresAt();
        }

        if ($this->profile) {
            return $this->profile->getConfig()->getExpiresAt();
        }

        return null;
    }

    public function getDate(): ?\DateTime
    {
        return $this->date;
    }

    public function setDate(?\DateTime $date): void
    {
        $this->date = $date;
    }

    #[Groups(['publication:read'])]
    public function getMapOptions(): MapOptions
    {
        if ($this->profile) {
            return $this->profile->getConfig()->getMapOptions()->mergeWith($this->config->getMapOptions());
        }

        return $this->config->getMapOptions();
    }

    #[Groups(['publication:read'])]
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

    #[Assert\Callback]
    public function validateNoParentRecursion(ExecutionContextInterface $context, $payload): void
    {
        if ($this->hasRecursiveParent()) {
            $context
                ->buildViolation('Publication has circular parenthood')
                ->atPath('parent')
                ->addViolation();
        }
    }

    private function hasRecursiveParent(): bool
    {
        $parents = [];
        $p = $this;
        while ($p) {
            if (isset($parents[$p->getId()])) {
                return true;
            }
            $parents[$p->getId()] = true;
            $p = $p->getParent();
        }

        return false;
    }
}
