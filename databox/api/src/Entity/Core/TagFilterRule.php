<?php

declare(strict_types=1);

namespace App\Entity\Core;

use Alchemy\AuthBundle\Security\JwtUser;
use Alchemy\CoreBundle\Entity\AbstractUuidEntity;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use App\Api\Model\Input\TagFilterRuleInput;
use App\Api\Model\Output\TagFilterRuleOutput;
use App\Api\Provider\TagFilterRuleCollectionProvider;
use App\Entity\Traits\CreatedAtTrait;
use App\Entity\Traits\UpdatedAtTrait;
use App\Repository\Core\TagFilterRuleRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection as DoctrineCollection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Doctrine\UuidType;

#[ApiResource(
    shortName: 'tag-filter-rule',
    operations: [
        new Get(security: 'is_granted("READ", object)'),
        new Put(security: 'is_granted("EDIT", object)'),
        new Delete(security: 'is_granted("DELETE", object)'),
        new GetCollection(),
        new Post(securityPostDenormalize: 'is_granted("CREATE", object)'),
    ],
    normalizationContext: ['groups' => ['_',
        TagFilterRule::GROUP_READ,
        Tag::GROUP_READ],
    ],
    input: TagFilterRuleInput::class,
    output: TagFilterRuleOutput::class,
    security: 'is_granted("'.JwtUser::IS_AUTHENTICATED_FULLY.'")',
    provider: TagFilterRuleCollectionProvider::class,
)]
#[ORM\Table]
#[ORM\Index(columns: ['user_type', 'user_id'], name: 'tfr_user_idx')]
#[ORM\Index(columns: ['object_type', 'object_id'], name: 'tfr_object_idx')]
#[ORM\Index(columns: ['user_type'], name: 'tfr_user_type_idx')]
#[ORM\UniqueConstraint(name: 'tfr_uniq_ace', columns: ['user_type', 'user_id', 'object_type', 'object_id'])]
#[ORM\Entity(repositoryClass: TagFilterRuleRepository::class)]
class TagFilterRule extends AbstractUuidEntity
{
    use CreatedAtTrait;
    use UpdatedAtTrait;

    final public const GROUP_READ = 'tfr:read';
    final public const GROUP_LIST = 'tfr:index';

    final public const TYPE_USER = 0;
    final public const TYPE_GROUP = 1;
    final public const TYPE_WORKSPACE = 0;
    final public const TYPE_COLLECTION = 1;

    final public const OBJECT_CLASSES = [
        self::TYPE_WORKSPACE => Workspace::class,
        self::TYPE_COLLECTION => Collection::class,
    ];

    #[ORM\Column(type: Types::SMALLINT)]
    protected ?int $userType = null;

    #[ORM\Column(type: Types::STRING, length: 36, nullable: true)]
    protected ?string $userId = null;

    #[ORM\Column(type: Types::SMALLINT)]
    protected int $objectType;

    #[ORM\Column(type: UuidType::NAME, nullable: false)]
    protected string $objectId;

    #[ORM\JoinTable(name: 'tfr_includes')]
    #[ORM\ManyToMany(targetEntity: Tag::class)]
    protected ?DoctrineCollection $include = null;

    #[ORM\JoinTable(name: 'tfr_excludes')]
    #[ORM\ManyToMany(targetEntity: Tag::class)]
    protected ?DoctrineCollection $exclude = null;

    public function __construct()
    {
        parent::__construct();

        $this->include = new ArrayCollection();
        $this->exclude = new ArrayCollection();
    }

    public function getUserType(): ?int
    {
        return $this->userType;
    }

    public function setUserType(?int $userType): void
    {
        $this->userType = $userType;
    }

    public function getUserId(): ?string
    {
        return $this->userId;
    }

    public function setUserId(?string $userId): void
    {
        $this->userId = $userId;
    }

    public function getObjectType(): int
    {
        return $this->objectType;
    }

    public function setObjectType(int $objectType): void
    {
        $this->objectType = $objectType;
    }

    public function getObjectId(): string
    {
        return $this->objectId;
    }

    public function setObjectId(string $objectId): void
    {
        $this->objectId = $objectId;
    }

    /**
     * @return Tag[]
     */
    public function getInclude(): DoctrineCollection
    {
        return $this->include;
    }

    /**
     * @return Tag[]
     */
    public function getExclude(): DoctrineCollection
    {
        return $this->exclude;
    }

    public function setInclude(iterable $include): void
    {
        $this->include->clear();
        foreach ($include as $item) {
            $this->include->add($item);
        }
    }

    public function setExclude(iterable $exclude): void
    {
        $this->exclude->clear();
        foreach ($exclude as $item) {
            $this->exclude->add($item);
        }
    }
}
