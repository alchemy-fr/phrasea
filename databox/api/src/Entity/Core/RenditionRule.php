<?php

declare(strict_types=1);

namespace App\Entity\Core;

use Alchemy\AuthBundle\Security\JwtUser;
use Alchemy\CoreBundle\Entity\AbstractUuidEntity;
use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use App\Api\Model\Input\RenditionRuleInput;
use App\Api\Model\Output\RenditionRuleOutput;
use Alchemy\CoreBundle\Entity\Traits\CreatedAtTrait;
use Alchemy\CoreBundle\Entity\Traits\UpdatedAtTrait;
use App\Repository\Core\RenditionRuleRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection as DoctrineCollection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Doctrine\UuidType;

#[ApiResource(
    shortName: 'rendition-rule',
    operations: [
        new Get(security: 'is_granted("READ", object)'),
        new Delete(security: 'is_granted("DELETE", object)'),
        new Put(security: 'is_granted("EDIT", object)'),
        new Patch(security: 'is_granted("EDIT", object)'),
        new GetCollection(),
        new Post(securityPostDenormalize: 'is_granted("CREATE", object)'),
    ],
    normalizationContext: [
        'groups' => [
            RenditionRule::GROUP_LIST,
            RenditionClass::GROUP_READ,
        ],
    ],
    input: RenditionRuleInput::class,
    output: RenditionRuleOutput::class,
    security: 'is_granted("'.JwtUser::IS_AUTHENTICATED_FULLY.'")',
)]
#[ORM\Table]
#[ORM\Index(columns: ['user_type', 'user_id'], name: 'rr_user_idx')]
#[ORM\Index(columns: ['object_type', 'object_id'], name: 'rr_object_idx')]
#[ORM\Index(columns: ['user_type'], name: 'rr_user_type_idx')]
#[ORM\UniqueConstraint(name: 'rend_uniq_rule', columns: ['user_type', 'user_id', 'object_type', 'object_id'])]
#[ORM\Entity(repositoryClass: RenditionRuleRepository::class)]
#[ApiFilter(SearchFilter::class, properties: ['allowed' => 'exact', 'userType' => 'exact', 'userId' => 'exact', 'objectType' => 'exact', 'objectId' => 'exact'])]
class RenditionRule extends AbstractUuidEntity
{
    use CreatedAtTrait;
    use UpdatedAtTrait;
    final public const string GROUP_READ = 'rendrule:read';
    final public const string GROUP_LIST = 'rendrule:index';

    final public const int TYPE_USER = 0;
    final public const int TYPE_GROUP = 1;
    final public const int TYPE_WORKSPACE = 0;
    final public const int TYPE_COLLECTION = 1;

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

    /**
     * @var RenditionClass[]|DoctrineCollection
     */
    #[ORM\JoinTable(name: 'sdr_allowed')]
    #[ORM\ManyToMany(targetEntity: RenditionClass::class)]
    protected ?DoctrineCollection $allowed = null;

    public function __construct()
    {
        parent::__construct();

        $this->allowed = new ArrayCollection();
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
     * @return RenditionClass[]
     */
    public function getAllowed(): DoctrineCollection
    {
        return $this->allowed;
    }

    public function setAllowed(iterable $allowed): void
    {
        $this->allowed->clear();
        foreach ($allowed as $item) {
            $this->allowed->add($item);
        }
    }
}
