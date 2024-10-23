<?php

declare(strict_types=1);

namespace App\Entity\Core;

use Alchemy\AclBundle\AclObjectInterface;
use Alchemy\AuthBundle\Security\JwtUser;
use Alchemy\CoreBundle\Entity\AbstractUuidEntity;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use App\Api\Model\Input\AttributeClassInput;
use App\Api\Provider\AttributeClassCollectionProvider;
use App\Entity\Traits\CreatedAtTrait;
use App\Entity\Traits\WorkspaceTrait;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection as DoctrineCollection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

#[ApiResource(
    shortName: 'attribute-class',
    operations: [
        new Get(security: 'is_granted("READ", object)'),
        new Delete(security: 'is_granted("DELETE", object)'),
        new Put(security: 'is_granted("EDIT", object)'),
        new Patch(security: 'is_granted("EDIT", object)'),
        new GetCollection(),
        new Post(securityPostDenormalize: 'is_granted("CREATE", object)'),
    ],
    normalizationContext: [
        'groups' => [AttributeClass::GROUP_LIST],
    ],
    input: AttributeClassInput::class,
    security: 'is_granted("'.JwtUser::IS_AUTHENTICATED_FULLY.'")',
    provider: AttributeClassCollectionProvider::class,
)]
#[ORM\Table]
#[ORM\UniqueConstraint(name: 'uniq_class_ws_name', columns: ['workspace_id', 'name'])]
#[ORM\UniqueConstraint(name: 'uniq_class_ws_key', columns: ['workspace_id', 'key'])]
#[ORM\Entity]
class AttributeClass extends AbstractUuidEntity implements AclObjectInterface, \Stringable
{
    use CreatedAtTrait;
    use WorkspaceTrait;
    final public const string GROUP_READ = 'attrclass:read';
    final public const string GROUP_LIST = 'attrclass:index';

    /**
     * Override trait for annotation.
     */
    #[ORM\ManyToOne(targetEntity: Workspace::class)]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['_'])]
    protected ?Workspace $workspace = null;

    #[Groups([AttributeClass::GROUP_LIST, AttributeDefinition::GROUP_LIST, AttributeDefinition::GROUP_READ])]
    #[ORM\Column(type: Types::STRING, length: 80)]
    private ?string $name = null;

    #[Groups([AttributeClass::GROUP_LIST])]
    #[ORM\Column(type: Types::BOOLEAN)]
    private ?bool $editable = null;

    #[Groups([AttributeClass::GROUP_LIST])]
    #[ORM\Column(type: Types::BOOLEAN, nullable: false)]
    private ?bool $public = null;

    /**
     * @var AttributeDefinition[]
     */
    #[ORM\OneToMany(mappedBy: 'class', targetEntity: AttributeDefinition::class, cascade: ['remove'])]
    protected ?DoctrineCollection $definitions = null;

    /**
     * Unique key by workspace. Used to prevent duplicates.
     */
    #[ORM\Column(type: Types::STRING, length: 150, nullable: true)]
    private ?string $key = null;

    #[Groups([AttributeClass::GROUP_READ])]
    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $labels = null;

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

    public function isEditable(): bool
    {
        return $this->editable;
    }

    public function setEditable(bool $editable): void
    {
        $this->editable = $editable;
    }

    public function getAclOwnerId(): string
    {
        return '';
    }

    public function getKey(): ?string
    {
        return $this->key;
    }

    public function setKey(?string $key): void
    {
        $this->key = $key;
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
