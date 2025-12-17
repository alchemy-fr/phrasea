<?php

declare(strict_types=1);

namespace App\Entity\Core;

use Alchemy\AuthBundle\Security\JwtUser;
use Alchemy\CoreBundle\Entity\AbstractUuidEntity;
use Alchemy\CoreBundle\Entity\Traits\CreatedAtTrait;
use Alchemy\TrackBundle\LoggableChangeSetInterface;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use App\Api\Provider\RenditionPolicyCollectionProvider;
use App\Entity\Traits\WorkspaceTrait;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection as DoctrineCollection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource(
    shortName: 'rendition-policy',
    operations: [
        new Get(security: 'is_granted("READ", object)'),
        new Delete(security: 'is_granted("DELETE", object)'),
        new Put(security: 'is_granted("EDIT", object)'),
        new Patch(security: 'is_granted("EDIT", object)'),
        new GetCollection(),
        new Post(securityPostDenormalize: 'is_granted("CREATE", object)'),
    ],
    normalizationContext: [
        'groups' => [RenditionPolicy::GROUP_LIST],
    ],
    security: 'is_granted("'.JwtUser::IS_AUTHENTICATED_FULLY.'")',
    provider: RenditionPolicyCollectionProvider::class,
)]
#[ORM\Table]
#[ORM\UniqueConstraint(name: 'rend_policy_uniq', columns: ['workspace_id', 'name'])]
#[UniqueEntity(
    fields: ['workspace', 'name'],
    errorPath: 'name',
)]
#[ORM\Entity]
class RenditionPolicy extends AbstractUuidEntity implements \Stringable, LoggableChangeSetInterface
{
    use CreatedAtTrait;
    use WorkspaceTrait;
    final public const int OBJECT_INDEX = 17;
    final public const string GROUP_READ = 'rendpol:r';
    final public const string GROUP_LIST = 'rendpol:i';

    /**
     * Override trait for annotation.
     */
    #[ORM\ManyToOne(targetEntity: Workspace::class, inversedBy: 'renditionPolicies')]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['_'])]
    #[Assert\NotNull]
    protected ?Workspace $workspace = null;

    #[Groups([RenditionPolicy::GROUP_LIST, RenditionPolicy::GROUP_READ, RenditionDefinition::GROUP_LIST, RenditionDefinition::GROUP_READ])]
    #[ORM\Column(type: Types::STRING, length: 80)]
    #[Assert\NotNull]
    private ?string $name = null;

    #[Groups([RenditionPolicy::GROUP_LIST, RenditionPolicy::GROUP_READ])]
    #[ORM\Column(type: Types::BOOLEAN, nullable: false)]
    private bool $public = false;

    #[Groups([RenditionPolicy::GROUP_LIST, RenditionPolicy::GROUP_READ])]
    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $labels = null;

    /**
     * @var RenditionDefinition[]|DoctrineCollection
     */
    #[ORM\OneToMany(mappedBy: 'policy', targetEntity: RenditionDefinition::class, cascade: ['remove'])]
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
