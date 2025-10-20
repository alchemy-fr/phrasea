<?php

declare(strict_types=1);

namespace App\Entity\Core;

use Alchemy\AclBundle\AclObjectInterface;
use Alchemy\CoreBundle\Entity\AbstractUuidEntity;
use Alchemy\CoreBundle\Entity\Traits\CreatedAtTrait;
use Alchemy\CoreBundle\Entity\Traits\UpdatedAtTrait;
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
            normalizationContext: [
                'groups' => [Workspace::GROUP_READ],
            ],
            security: 'is_granted("READ", object)'
        ),
        new Put(
            normalizationContext: [
                'groups' => [Workspace::GROUP_READ],
            ],
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
        new GetCollection(),
        new Get(
            uriTemplate: '/workspaces-by-slug/{slug}',
            uriVariables: [
                'slug' => 'slug',
            ],
            controller: GetWorkspaceBySlugAction::class,
            name: 'get_by_slug'
        ),
        new Post(
            normalizationContext: [
                'groups' => [Workspace::GROUP_READ],
            ],
            securityPostDenormalize: 'is_granted("'.AbstractVoter::CREATE.'", object)',
        ),
    ],
    normalizationContext: [
        'groups' => [Workspace::GROUP_LIST],
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
class Workspace extends AbstractUuidEntity implements SoftDeleteableInterface, AclObjectInterface, WithOwnerIdInterface, \Stringable
{
    use CreatedAtTrait;
    use UpdatedAtTrait;
    use OwnerIdTrait;
    use DeletedAtTrait;
    use TranslationsTrait;

    final public const string GROUP_READ = 'workspace:r';
    final public const string GROUP_LIST = 'workspace:i';
    public const string CONFIG_ANALYZERS = 'analyzers';

    #[ORM\Column(type: Types::STRING, length: 255, nullable: false)]
    #[Assert\NotBlank]
    private ?string $name = null;

    #[ORM\Column(type: Types::STRING, length: 50, unique: true, nullable: false)]
    #[Assert\NotBlank]
    #[Assert\Length(min: 2, max: 50)]
    #[Assert\Regex(
        pattern: '/^[a-z0-9][a-z0-9-]*[a-z0-9]$/',
        message: 'Invalid slug. Should match: my-workspace01'
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
        if (null !== $this->deletedAt) {
            return sprintf('(being deleted...) %s', $this->name);
        }

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

    public function getFileAnalyzers(): ?string
    {
        return $this->config[self::CONFIG_ANALYZERS] ?? null;
    }

    public function setFileAnalyzers(?string $analyzers): void
    {
        $this->config[self::CONFIG_ANALYZERS] = $analyzers;
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
