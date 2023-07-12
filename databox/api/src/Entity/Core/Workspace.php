<?php

declare(strict_types=1);

namespace App\Entity\Core;

use Alchemy\AclBundle\AclObjectInterface;
use ApiPlatform\Core\Annotation\ApiProperty;
use App\Api\Model\Input\WorkspaceInput;
use App\Api\Model\Output\WorkspaceOutput;
use App\Api\DtoTransformer\WorkspaceDtoTransformer;
use App\Controller\Core\FlushWorkspaceAction;
use App\Controller\Core\GetWorkspaceBySlugAction;
use App\Doctrine\Listener\SoftDeleteableInterface;
use App\Entity\AbstractUuidEntity;
use App\Entity\Traits\CreatedAtTrait;
use App\Entity\Traits\DeletedAtTrait;
use App\Entity\Traits\UpdatedAtTrait;
use App\Entity\WithOwnerIdInterface;
use App\Repository\Core\WorkspaceRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection as DoctrineCollection;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Put;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\GetCollection;

#[ApiResource(
    shortName: 'workspace',
    operations: [
        new Get(
            normalizationContext: [
                'groups' => ['workspace:read'],
            ],
            security: 'is_granted("READ", object)'
        ),
        new Put(
            normalizationContext: [
                'groups' => ['workspace:read'],
            ],
            security: 'is_granted("EDIT", object)'
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
                'groups' => ['workspace:read'],
            ],
            security: 'is_granted("ROLE_ADMIN")'
        )
    ],
    normalizationContext: [
        'groups' => ['workspace:index'],
    ],
    input: WorkspaceInput::class,
    output: WorkspaceOutput::class,
    provider: WorkspaceDtoTransformer::class,
)]
#[ORM\Entity(repositoryClass: WorkspaceRepository::class)]
#[Gedmo\SoftDeleteable(fieldName: 'deletedAt', hardDelete: false)]
class Workspace extends AbstractUuidEntity implements SoftDeleteableInterface, AclObjectInterface, WithOwnerIdInterface, \Stringable
{
    use CreatedAtTrait;
    use UpdatedAtTrait;
    use DeletedAtTrait;

    #[ORM\Column(type: 'string', length: 255, nullable: false)]
    private ?string $name = null;

    #[ORM\Column(type: 'string', length: 50, nullable: false)]
    private ?string $slug = null;

    #[ORM\Column(type: 'string', length: 255)]
    private ?string $ownerId = null;

    #[ORM\Column(type: 'boolean', nullable: false)]
    private bool $public = false;

    #[ORM\Column(type: 'json', nullable: false)]
    private array $config = [];

    #[ORM\Column(type: 'json', nullable: false)]
    private array $enabledLocales = [];

    #[ORM\Column(type: 'json', nullable: false)]
    private ?array $localeFallbacks = ['en'];

    /**
     * @var Collection[]
     */
    #[ORM\OneToMany(targetEntity: Collection::class, mappedBy: 'workspace')]
    protected ?DoctrineCollection $collections = null;

    /**
     * @var Tag[]
     */
    #[ORM\OneToMany(targetEntity: Tag::class, mappedBy: 'workspace')]
    protected ?DoctrineCollection $tags = null;

    /**
     * @var RenditionClass[]
     */
    #[ORM\OneToMany(targetEntity: RenditionClass::class, mappedBy: 'workspace')]
    protected ?DoctrineCollection $renditionClasses = null;

    /**
     * @var RenditionDefinition[]
     */
    #[ORM\OneToMany(targetEntity: RenditionDefinition::class, mappedBy: 'workspace')]
    protected ?DoctrineCollection $renditionDefinitions = null;

    /**
     * @var AttributeDefinition[]
     */
    #[ORM\OneToMany(targetEntity: AttributeDefinition::class, mappedBy: 'workspace')]
    protected ?DoctrineCollection $attributeDefinitions = null;

    /**
     * @var File[]
     */
    #[ORM\OneToMany(targetEntity: File::class, mappedBy: 'workspace')]
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

    public function getOwnerId(): ?string
    {
        return $this->ownerId;
    }

    public function setOwnerId(?string $ownerId): void
    {
        $this->ownerId = $ownerId;
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

    public function getLocaleFallbacks(): ?array
    {
        return $this->localeFallbacks;
    }

    public function setLocaleFallbacks(?array $localeFallbacks): void
    {
        $this->localeFallbacks = $localeFallbacks;
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
