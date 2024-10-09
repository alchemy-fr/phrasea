<?php

declare(strict_types=1);

namespace App\Entity\Core;

use Alchemy\AuthBundle\Security\JwtUser;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;

use App\Api\Provider\RenditionClassCollectionProvider;
use Alchemy\CoreBundle\Entity\AbstractUuidEntity;
use App\Entity\Traits\CreatedAtTrait;
use App\Entity\Traits\WorkspaceTrait;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection as DoctrineCollection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

#[ApiResource(
    shortName: 'rendition-class',
    operations: [
        new Get(security: 'is_granted("READ", object)'),
        new Delete(security: 'is_granted("DELETE", object)'),
        new Put(security: 'is_granted("EDIT", object)'),
        new Patch(security: 'is_granted("EDIT", object)'),
        new GetCollection(),
        new Post(securityPostDenormalize: 'is_granted("CREATE", object)'),
    ],
    normalizationContext: [
        'groups' => [RenditionClass::GROUP_LIST],
    ],
    security: 'is_granted("'.JwtUser::IS_AUTHENTICATED_FULLY.'")',
    provider: RenditionClassCollectionProvider::class,
)]
#[ORM\Table]
#[ORM\UniqueConstraint(name: 'rend_class_uniq', columns: ['workspace_id', 'name'])]
#[ORM\Entity]
class RenditionClass extends AbstractUuidEntity implements \Stringable
{
    use CreatedAtTrait;
    use WorkspaceTrait;
    final public const GROUP_READ = 'rendclass:read';
    final public const GROUP_LIST = 'rendclass:index';

    /**
     * Override trait for annotation.
     */
    #[ORM\ManyToOne(targetEntity: Workspace::class, inversedBy: 'renditionClasses')]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['_'])]
    protected ?Workspace $workspace = null;

    #[Groups([RenditionClass::GROUP_LIST, RenditionClass::GROUP_READ])]
    #[ORM\Column(type: Types::STRING, length: 80)]
    private ?string $name = null;

    #[Groups([RenditionClass::GROUP_LIST, RenditionClass::GROUP_READ])]
    #[ORM\Column(type: Types::BOOLEAN, nullable: false)]
    private bool $public = false;

    #[Groups([RenditionClass::GROUP_LIST, RenditionClass::GROUP_READ])]
    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $labels = null;

    /**
     * @var RenditionDefinition[]
     */
    #[ORM\OneToMany(mappedBy: 'class', targetEntity: RenditionDefinition::class, cascade: ['remove'])]
    protected ?DoctrineCollection $definitions = null;

    public function __construct()
    {
        parent::__construct();
        $this->definitions = new ArrayCollection();
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function __toString(): string
    {
        return sprintf('%s (%s)', $this->getName(), $this->getWorkspace()->getName());
    }

    public function isPublic(): bool
    {
        return $this->public;
    }

    public function setPublic(bool $public): void
    {
        $this->public = $public;
    }

    public function getLabels(): ?array
    {
        return $this->labels;
    }

    public function setLabels(?array $labels): void
    {
        $this->labels = $labels;
    }
}
