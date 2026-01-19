<?php

declare(strict_types=1);

namespace App\Entity;

use Alchemy\AclBundle\AclObjectInterface;
use ApiPlatform\Doctrine\Orm\Filter\OrderFilter;
use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use App\Api\Provider\PublicationProvider;
use App\Controller\GetPublicationSlugAvailabilityAction;
use App\Controller\SortAssetsAction;
use App\Entity\Traits\CapabilitiesTrait;
use App\Entity\Traits\ClientAnnotationsTrait;
use App\Filter\PublicationFilter;
use App\Model\LayoutOptions;
use App\Model\MapOptions;
use App\Security\Voter\PublicationVoter;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Doctrine\UuidType;
use Ramsey\Uuid\Uuid;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Serializer\Annotation\MaxDepth;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

#[ApiResource(
    operations: [
        new Get(
            uriTemplate: '/publications/{id}',
            uriVariables: [
                'id',
            ],
            security: 'is_granted("'.PublicationVoter::READ.'", object)',
            name: self::GET_PUBLICATION_ROUTE_NAME,
            provider: PublicationProvider::class,
        ),
        new Put(security: 'is_granted("'.PublicationVoter::EDIT.'", object)'),
        new Delete(security: 'is_granted("'.PublicationVoter::DELETE.'", object)'),
        new Post(
            uriTemplate: '/publications/{id}/sort-assets',
            defaults: [
                '_api_receive' => false,
                '_api_respond' => true,
            ],
            controller: SortAssetsAction::class
        ),
        new GetCollection(
            normalizationContext: [
                'groups' => [self::GROUP_INDEX],
            ]
        ),
        new Post(
            securityPostDenormalize: 'is_granted("'.PublicationVoter::CREATE.'", object)'
        ),
        new GetCollection(
            uriTemplate: '/publications/slug-availability/{slug}',
            defaults: ['_api_receive' => false, 'input' => false, 'output' => false],
            controller: GetPublicationSlugAvailabilityAction::class,
            openapiContext: [
                'summary' => 'Check whether a slug is available or not.',
                'description' => 'Check whether a slug is available or not.',
                'responses' => [
                    '200' => [
                        'content' => [
                            'application/json' => [
                                'schema' => ['type' => 'boolean'],
                            ],
                        ],
                    ],
                ],
                'parameters' => [
                    [
                        'in' => 'path',
                        'name' => 'slug',
                        'type' => 'string',
                        'required' => true,
                        'description' => 'The slug to verify',
                    ],
                ],
            ],
            paginationEnabled: false,
            normalizationContext: [
                'groups' => [self::GROUP_INDEX],
            ],
        ),
    ],
    normalizationContext: [
        'groups' => [self::GROUP_INDEX, self::GROUP_READ],
    ],
    denormalizationContext: [
        'deep_object_to_populate' => true,
        'groups' => [self::GROUP_WRITE],
    ],
    order: ['title' => 'ASC']
)]
#[ORM\Entity]
#[ApiFilter(filterClass: OrderFilter::class, properties: ['title' => 'ASC', 'createdAt' => 'DESC', 'updatedAt' => 'DESC'], arguments: ['orderParameterName' => 'order'])]
#[ApiFilter(filterClass: PublicationFilter::class, properties: ['flatten', 'parentId', 'profileId', 'mine', 'expired'])]
#[ApiFilter(filterClass: PublicationFilter::class, properties: ['flatten', 'parentId', 'profileId', 'mine', 'expired'])]
#[ApiFilter(filterClass: SearchFilter::class, properties: ['title' => 'ipartial', 'description' => 'ipartial'])]
class Publication implements AclObjectInterface, \Stringable
{
    use CapabilitiesTrait;
    use ClientAnnotationsTrait;

    final public const string GET_PUBLICATION_ROUTE_NAME = 'get_publication';

    private const string GROUP_PREFIX = 'publication:';
    final public const string GROUP_READ = self::GROUP_PREFIX.'r';
    final public const string GROUP_WRITE = self::GROUP_PREFIX.'w';
    final public const string GROUP_ADMIN_READ = 'admin:'.self::GROUP_READ;
    final public const string GROUP_ADMIN_WRITE = 'admin:'.self::GROUP_WRITE;
    final public const string GROUP_INDEX = self::GROUP_PREFIX.'i';

    final public const null SECURITY_METHOD_NONE = null;
    final public const string SECURITY_METHOD_PASSWORD = 'password';
    final public const string SECURITY_METHOD_AUTHENTICATION = 'authentication';

    #[ApiProperty(identifier: true)]
    #[Groups(['_', self::GROUP_INDEX, self::GROUP_READ, Asset::GROUP_READ])]
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    private string $id;

    #[ORM\Column(type: Types::STRING, length: 255)]
    #[Groups([self::GROUP_INDEX, self::GROUP_READ, self::GROUP_WRITE])]
    #[Assert\Length(max: 255)]
    private ?string $title = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups([self::GROUP_INDEX, self::GROUP_READ, self::GROUP_WRITE])]
    private ?string $description = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    #[Groups([self::GROUP_INDEX, self::GROUP_READ, self::GROUP_WRITE])]
    private ?array $translations = null;

    /**
     * @var Asset[]|Collection
     */
    #[Groups([self::GROUP_READ])]
    #[MaxDepth(1)]
    #[ORM\OneToMany(mappedBy: 'publication', targetEntity: Asset::class, cascade: ['remove'])]
    #[ORM\OrderBy(['position' => 'ASC', 'createdAt' => 'ASC'])]
    private Collection $assets;

    #[ORM\ManyToOne(targetEntity: PublicationProfile::class)]
    #[Groups([self::GROUP_READ, self::GROUP_WRITE])]
    private ?PublicationProfile $profile = null;

    #[ORM\ManyToOne(targetEntity: Asset::class)]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    #[Groups([self::GROUP_READ, self::GROUP_WRITE])]
    private ?Asset $package = null;

    #[ORM\ManyToOne(targetEntity: Asset::class)]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    #[Groups([self::GROUP_READ, self::GROUP_WRITE])]
    private ?Asset $cover = null;

    #[Groups([self::GROUP_READ, self::GROUP_WRITE])]
    private ?string $packageUrl = null;

    #[Groups([self::GROUP_READ, self::GROUP_WRITE])]
    private ?string $archiveDownloadUrl = null;

    #[ORM\Column(type: Types::STRING, nullable: true)]
    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_ADMIN_WRITE])]
    private ?string $ownerId = null;

    #[Groups(['_', self::GROUP_INDEX, Asset::GROUP_READ])]
    private bool $authorized = false;

    /**
     * Password identifier for the current publication branch.
     */
    #[Groups(['_', self::GROUP_INDEX, Asset::GROUP_READ])]
    private ?string $securityContainerId = null;

    #[Groups(['_', self::GROUP_INDEX, Asset::GROUP_READ])]
    private ?string $authorizationError = null;

    #[Groups([self::GROUP_READ, self::GROUP_WRITE])]
    #[MaxDepth(1)]
    #[ORM\ManyToOne(targetEntity: Publication::class, inversedBy: 'children')]
    #[ORM\JoinColumn(nullable: true)]
    private ?Publication $parent = null;

    /**
     * @var Publication[]|Collection
     */
    #[ORM\JoinTable(name: 'publication_children')]
    #[ORM\JoinColumn(name: 'parent_id', referencedColumnName: 'id')]
    #[ORM\InverseJoinColumn(name: 'child_id', referencedColumnName: 'id')]
    #[Groups([self::GROUP_READ])]
    #[MaxDepth(1)]
    #[ORM\OneToMany(targetEntity: Publication::class, mappedBy: 'parent', cascade: ['remove'])]
    #[ORM\OrderBy(['title' => 'ASC'])]
    private Collection $children;

    #[ORM\Embedded(class: PublicationConfig::class)]
    #[Groups([self::GROUP_INDEX, self::GROUP_ADMIN_READ])]
    private PublicationConfig $config;

    /**
     * URL slug.
     */
    #[Groups(['_', self::GROUP_INDEX, self::GROUP_WRITE])]
    #[ORM\Column(type: Types::STRING, length: 100, unique: true, nullable: true)]
    #[Assert\Length(max: 100)]
    protected ?string $slug = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    #[Groups([self::GROUP_READ])]
    private ?\DateTimeImmutable $date = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    #[Groups([self::GROUP_READ])]
    private \DateTimeImmutable $createdAt;

    #[ApiProperty(writable: false)]
    #[Groups([self::GROUP_READ, Asset::GROUP_READ])]
    private ?string $cssLink = null;

    #[ORM\Column(type: Types::STRING, length: 36, nullable: true)]
    private ?string $zippyId = null;

    #[ORM\Column(type: Types::STRING, length: 32, nullable: true)]
    private ?string $zippyHash = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->assets = new ArrayCollection();
        $this->children = new ArrayCollection();
        $this->config = new PublicationConfig();
        $this->id = Uuid::uuid4()->toString();
    }

    public function getId(): string
    {
        return $this->id;
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

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    #[Groups([self::GROUP_INDEX, Asset::GROUP_READ])]
    public function isEnabled(): bool
    {
        if ($this->profile && null === $this->config->isEnabled()) {
            return true === $this->profile->getConfig()->isEnabled();
        }

        return true === $this->config->isEnabled();
    }

    #[Groups([self::GROUP_INDEX])]
    public function isPubliclyListed(): bool
    {
        if ($this->profile && null === $this->config->isPubliclyListed()) {
            return true === $this->profile->getConfig()->isPubliclyListed();
        }

        return true === $this->config->isPubliclyListed();
    }

    #[Groups([self::GROUP_READ])]
    public function getLayout(): string
    {
        return $this->config->getLayout() ?? ($this->profile && $this->profile->getConfig()->getLayout() ? $this->profile->getConfig()->getLayout() : 'grid');
    }

    #[Groups([self::GROUP_ADMIN_READ])]
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
    #[Groups([self::GROUP_READ])]
    public function getUrls(): array
    {
        $urls = $this->config->getUrls();
        if ($this->profile && !empty($this->profile->getConfig()->getUrls())) {
            $urls = array_merge($this->profile->getConfig()->getUrls(), $urls);
        }

        return Url::mapUrls($urls);
    }

    #[Groups([self::GROUP_READ])]
    public function getCopyrightText(): ?string
    {
        return $this->config->getCopyrightText() ?? $this->profile?->getConfig()->getCopyrightText();
    }

    #[Groups([self::GROUP_READ, Asset::GROUP_READ])]
    public function getTheme(): ?string
    {
        return $this->config->getTheme() ?? $this->profile?->getConfig()->getTheme();
    }

    public function isPublic(): bool
    {
        return null === $this->getSecurityMethod();
    }

    #[Groups(['_', self::GROUP_INDEX, Asset::GROUP_READ])]
    public function getSecurityMethod(): ?string
    {
        return $this->config->getSecurityMethod() ?? $this->profile?->getConfig()->getSecurityMethod();
    }

    #[Groups([self::GROUP_ADMIN_READ])]
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

    #[Groups([self::GROUP_INDEX])]
    public function getChildrenCount(): int
    {
        return $this->children->count();
    }

    public function getParent(): ?self
    {
        return $this->parent;
    }

    #[Groups([self::GROUP_READ])]
    public function getParentId(): ?string
    {
        return $this->parent?->getId();
    }

    #[Groups([self::GROUP_READ])]
    public function getRootPublication(): ?self
    {
        $parent = $this;
        while ($parent->parent) {
            $parent = $parent->parent;
        }

        return $parent !== $this ? $parent : null;
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

    #[Groups([self::GROUP_READ, Asset::GROUP_READ])]
    public function getTerms(): TermsConfig
    {
        if ($this->profile) {
            return $this->profile->getConfig()->getTerms()->mergeWith($this->config->getTerms());
        }

        return $this->config->getTerms();
    }

    #[Groups([self::GROUP_READ, Asset::GROUP_READ])]
    public function getDownloadTerms(): TermsConfig
    {
        if ($this->profile) {
            return $this->profile->getConfig()->getDownloadTerms()->mergeWith($this->config->getDownloadTerms());
        }

        return $this->config->getDownloadTerms();
    }

    #[Groups([self::GROUP_READ, Asset::GROUP_READ])]
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

    #[Groups([self::GROUP_READ, Asset::GROUP_READ])]
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

    #[Groups([self::GROUP_READ])]
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

    public function isVisible(?\DateTimeImmutable $now = null): bool
    {
        $now ??= new \DateTimeImmutable();

        return $this->isEnabled()
            && (null === $this->getBeginsAt() || $this->getBeginsAt() < $now)
            && (null === $this->getExpiresAt() || $this->getExpiresAt() > $now);
    }

    public function getBeginsAt(): ?\DateTimeImmutable
    {
        if (null !== $this->config->getBeginsAt()) {
            return $this->config->getBeginsAt();
        }

        return $this->profile?->getConfig()->getBeginsAt();

    }

    public function getExpiresAt(): ?\DateTimeImmutable
    {
        if (null !== $this->config->getExpiresAt()) {
            return $this->config->getExpiresAt();
        }

        return $this->profile?->getConfig()->getExpiresAt();

    }

    public function getDate(): ?\DateTimeImmutable
    {
        return $this->date;
    }

    public function setDate(?\DateTimeImmutable $date): void
    {
        $this->date = $date;
    }

    #[Groups([self::GROUP_READ])]
    public function getMapOptions(): MapOptions
    {
        if ($this->profile) {
            return $this->profile->getConfig()->getMapOptions()->mergeWith($this->config->getMapOptions());
        }

        return $this->config->getMapOptions();
    }

    #[Groups([self::GROUP_READ])]
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

    public function getTranslations(): ?array
    {
        return $this->translations;
    }

    public function setTranslations(?array $translations): void
    {
        $this->translations = $translations;
    }
}
