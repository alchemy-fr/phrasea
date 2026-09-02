<?php

declare(strict_types=1);

namespace App\Entity\Core;

use Alchemy\AclBundle\AclObjectInterface;
use Alchemy\CoreBundle\Entity\AbstractUuidEntity;
use Alchemy\CoreBundle\Entity\Traits\CreatedAtTrait;
use Alchemy\CoreBundle\Entity\Traits\UpdatedAtTrait;
use Alchemy\TrackBundle\LoggableChangeSetInterface;
use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use App\Api\Model\Input\WorkspaceInput;
use App\Api\Model\Output\WorkspaceOutput;
use App\Controller\Core\FlushWorkspaceAction;
use App\Controller\Core\GetWorkspaceBySlugAction;
use App\Controller\Core\SignWorkspaceTermsAction;
use App\Doctrine\Listener\SoftDeleteableInterface;
use App\Entity\Traits\DeletedAtTrait;
use App\Entity\Traits\OwnerIdTrait;
use App\Entity\Traits\TranslationsTrait;
use App\Entity\WithOwnerIdInterface;
use App\Repository\Core\WorkspaceRepository;
use App\Security\Voter\AbstractVoter;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection as DoctrineCollection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource(
    shortName: 'workspace',
    operations: [
        new Get(
            security: 'is_granted("READ", object)'
        ),
        new Put(
            securityPostDenormalize: 'is_granted("EDIT", object)'
        ),
        new Delete(security: 'is_granted("DELETE", object)'),
        new Post(
            uriTemplate: '/workspaces/{id}/flush',
            controller: FlushWorkspaceAction::class,
            security: 'is_granted("EDIT", object)',
            read: true,
            name: 'flush'
        ),
        new Post(
            uriTemplate: '/workspaces/{id}/terms/sign',
            controller: SignWorkspaceTermsAction::class,
            security: 'is_granted("READ", object)',
            read: true,
            deserialize: false,
            validate: false,
            name: 'sign_terms'
        ),
        new GetCollection(
            normalizationContext: [
                'groups' => [self::GROUP_LIST],
            ],
        ),
        new Get(
            uriTemplate: '/workspaces-by-slug/{slug}',
            uriVariables: [
                'slug' => 'slug',
            ],
            controller: GetWorkspaceBySlugAction::class,
            name: 'get_by_slug'
        ),
        new Post(
            securityPostDenormalize: 'is_granted("'.AbstractVoter::CREATE.'", object)',
        ),
    ],
    normalizationContext: [
        'groups' => [self::GROUP_LIST, self::GROUP_READ],
    ],
    input: WorkspaceInput::class,
    output: WorkspaceOutput::class,
)]
#[ORM\Entity(repositoryClass: WorkspaceRepository::class)]
#[ORM\Index(fields: ['public'], name: 'public_idx')]
#[Gedmo\SoftDeleteable(fieldName: 'deletedAt', hardDelete: false)]
#[UniqueEntity(fields: [
    'slug',
], message: 'Slug is already taken')]
class Workspace extends AbstractUuidEntity implements SoftDeleteableInterface, AclObjectInterface, WithOwnerIdInterface, \Stringable, LoggableChangeSetInterface
{
    use CreatedAtTrait;
    use UpdatedAtTrait;
    use OwnerIdTrait;
    use DeletedAtTrait;
    use TranslationsTrait;
    final public const int OBJECT_INDEX = 3;
    final public const string OBJECT_TYPE = 'workspace';

    final public const string GROUP_READ = 'workspace:r';
    final public const string GROUP_LIST = 'workspace:i';

    private const int CONFIG_DEFAULT_TRASH_RETENTION_DELAY = 30;
    private const string CONFIG_TRASH_RETENTION_DELAY = 'trashRetentionDelay';
    private const string CONFIG_ASSET_DEFAULT_STATUS = 'assetDefaultStatus';
    private const string CONFIG_FILE_ANALYSIS_REQUIRED = 'fileAnalysisRequired';
    private const string CONFIG_ATTACH_TERMS_TO_EXPORTS = 'attachTermsToExports';
    private const string CONFIG_LOGO = 'logo';

    final public const string TR_FIELD_NAME = 'name';

    #[ORM\Column(type: Types::STRING, length: 255, nullable: false)]
    #[Assert\NotBlank]
    private ?string $name = null;

    #[ORM\Column(type: Types::STRING, length: 50, unique: true, nullable: false)]
    #[Assert\NotBlank]
    #[Assert\Length(min: 2, max: 50)]
    #[Assert\Regex(
        pattern: '/^[a-z0-9][a-z0-9-]*[a-z0-9]$/',
        message: 'Invalid slug {{ value }}. Should match: my-workspace01'
    )]
    private ?string $slug = null;

    #[ORM\Column(type: Types::BOOLEAN, nullable: false)]
    private bool $public = false;

    #[ORM\Column(type: Types::JSON, nullable: false)]
    private array $config = [];

    #[ORM\Column(type: Types::JSON, nullable: false)]
    #[Assert\All([
        new Assert\NotBlank(),
        new Assert\Regex(
            pattern: '#^[a-z]{2}(_[A-Z]{2})?$#',
            message: 'Invalid locale format, must match "fr" or "fr_FR"'
        ),
    ])]
    private array $enabledLocales = ['en'];

    #[ORM\Column(type: Types::JSON, nullable: false)]
    private ?array $localeFallbacks = ['en'];

    /**
     * @var DoctrineCollection<Collection>
     */
    #[ORM\OneToMany(mappedBy: 'workspace', targetEntity: Collection::class)]
    protected ?DoctrineCollection $collections = null;

    /**
     * @var DoctrineCollection<Tag>
     */
    #[ORM\OneToMany(mappedBy: 'workspace', targetEntity: Tag::class)]
    protected ?DoctrineCollection $tags = null;

    /**
     * @var DoctrineCollection<RenditionPolicy>
     */
    #[ORM\OneToMany(mappedBy: 'workspace', targetEntity: RenditionPolicy::class)]
    protected ?DoctrineCollection $renditionPolicies = null;

    /**
     * @var DoctrineCollection<RenditionDefinition>
     */
    #[ORM\OneToMany(mappedBy: 'workspace', targetEntity: RenditionDefinition::class)]
    protected ?DoctrineCollection $renditionDefinitions = null;

    /**
     * @var AttributeDefinition[]
     */
    #[ORM\OneToMany(mappedBy: 'workspace', targetEntity: AttributeDefinition::class)]
    protected ?DoctrineCollection $attributeDefinitions = null;

    /**
     * @var File[]
     */
    #[ORM\OneToMany(mappedBy: 'workspace', targetEntity: File::class)]
    protected ?DoctrineCollection $files = null;

    public function __construct()
    {
        parent::__construct();
        $this->collections = new ArrayCollection();
        $this->tags = new ArrayCollection();
        $this->renditionPolicies = new ArrayCollection();
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

    public function __toString(): string
    {
        return $this->getName();
    }

    #[ApiProperty(readable: false, writable: false)]
    public function getAclOwnerId(): string
    {
        return $this->getOwnerId() ?? '';
    }

    public function getConfig(): array
    {
        return $this->config;
    }

    public function setConfig(array $config): void
    {
        $this->config = $config;
    }

    public function getTrashRetentionDelay(): int
    {
        return $this->config[self::CONFIG_TRASH_RETENTION_DELAY] ?? self::CONFIG_DEFAULT_TRASH_RETENTION_DELAY;
    }

    public function setTrashRetentionDelay(int $days): void
    {
        $this->config[self::CONFIG_TRASH_RETENTION_DELAY] = $days;
    }

    public function setAssetDefaultStatus(?AssetStatusEnum $status): void
    {
        if (AssetStatusEnum::Accepted === $status) {
            unset($this->config[self::CONFIG_ASSET_DEFAULT_STATUS]);

            return;
        }

        $this->config[self::CONFIG_ASSET_DEFAULT_STATUS] = $status->value;
    }

    public function getAssetDefaultStatus(): AssetStatusEnum
    {
        if (isset($this->config[self::CONFIG_ASSET_DEFAULT_STATUS])) {
            return AssetStatusEnum::tryFrom($this->config[self::CONFIG_ASSET_DEFAULT_STATUS]) ?? AssetStatusEnum::Accepted;
        }

        return AssetStatusEnum::Accepted;
    }

    public function isFileAnalysisRequired(): bool
    {
        return $this->config[self::CONFIG_FILE_ANALYSIS_REQUIRED] ?? false;
    }

    public function setFileAnalysisRequired(?bool $required): void
    {
        if (false === $required) {
            unset($this->config[self::CONFIG_FILE_ANALYSIS_REQUIRED]);

            return;
        }

        $this->config[self::CONFIG_FILE_ANALYSIS_REQUIRED] = $required;
    }

    public function isAttachTermsToExports(): bool
    {
        return $this->config[self::CONFIG_ATTACH_TERMS_TO_EXPORTS] ?? false;
    }

    public function setAttachTermsToExports(?bool $attach): void
    {
        if (true !== $attach) {
            unset($this->config[self::CONFIG_ATTACH_TERMS_TO_EXPORTS]);

            return;
        }

        $this->config[self::CONFIG_ATTACH_TERMS_TO_EXPORTS] = true;
    }

    public function getLogo(): ?string
    {
        return $this->config[self::CONFIG_LOGO] ?? null;
    }

    public function setLogo(?string $logo): void
    {
        $logo = null !== $logo ? trim($logo) : null;
        if (null === $logo || '' === $logo) {
            unset($this->config[self::CONFIG_LOGO]);

            return;
        }

        $this->config[self::CONFIG_LOGO] = $logo;
    }

    public function getEnabledLocales(): array
    {
        return $this->enabledLocales;
    }

    public function setEnabledLocales(array $enabledLocales): void
    {
        $this->enabledLocales = array_values($enabledLocales);
    }

    public function getSlug(): ?string
    {
        return $this->slug;
    }

    public function setSlug(?string $slug): void
    {
        $this->slug = $slug;
    }

    public function getLocaleFallbacks(): ?array
    {
        return $this->localeFallbacks;
    }

    public function setLocaleFallbacks(?array $localeFallbacks): void
    {
        $this->localeFallbacks = array_values($localeFallbacks);
    }

    public function isPublic(): bool
    {
        return $this->public;
    }

    public function setPublic(bool $public): void
    {
        $this->public = $public;
    }
}
