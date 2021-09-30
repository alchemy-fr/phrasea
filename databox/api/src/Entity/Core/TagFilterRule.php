<?php

declare(strict_types=1);

namespace App\Entity\Core;

use ApiPlatform\Core\Annotation\ApiResource;
use App\Entity\AbstractUuidEntity;
use App\Entity\Traits\CreatedAtTrait;
use App\Entity\Traits\UpdatedAtTrait;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\Collection as DoctrineCollection;
use App\Api\Model\Output\TagFilterRuleOutput;
use App\Api\Model\Input\TagFilterRuleInput;

/**
 * @ORM\Table(
 *     uniqueConstraints={@ORM\UniqueConstraint(name="tfr_uniq_ace", columns={"user_type", "user_id", "object_type", "object_id"})},
 *     indexes={
 *         @ORM\Index(name="tfr_user_idx", columns={"user_type", "user_id"}),
 *         @ORM\Index(name="tfr_object_idx", columns={"object_type", "object_id"}),
 *         @ORM\Index(name="tfr_user_type_idx", columns={"user_type"}),
 *     }
 * )
 * @ORM\Entity(repositoryClass="App\Repository\TagFilterRuleRepository")
 * @ApiResource(
 *  shortName="tag-filter-rule",
 *  attributes={"security"="is_granted('ROLE_USER')"},
 *  collectionOperations={
 *       "get",
 *       "post" = { "security_post_denormalize" = "is_granted('CREATE', object)" }
 *  },
 *  itemOperations={
 *       "get" = { "security" = "is_granted('READ', object)" },
 *       "put" = { "security" = "is_granted('EDIT', object)" },
 *       "delete" = { "security" = "is_granted('DELETE', object)" }
 *  },
 *  normalizationContext={"groups"={"_", "tfr:read", "tag:read"}},
 *  output=TagFilterRuleOutput::class,
 *  input=TagFilterRuleInput::class,
 * )
 */
class TagFilterRule extends AbstractUuidEntity
{
    use CreatedAtTrait;
    use UpdatedAtTrait;

    const TYPE_USER = 0;
    const TYPE_GROUP = 1;
    const TYPE_WORKSPACE = 0;
    const TYPE_COLLECTION = 1;

    const OBJECT_CLASSES = [
        self::TYPE_WORKSPACE => Workspace::class,
        self::TYPE_COLLECTION => Collection::class,
    ];

    /**
     * @ORM\Column(type="smallint")
     */
    protected ?int $userType = null;

    /**
     * @ORM\Column(type="string", length=36, nullable=true)
     */
    protected ?string $userId = null;

    /**
     * @ORM\Column(type="smallint")
     */
    protected int $objectType;

    /**
     * @ORM\Column(type="uuid", nullable=false)
     */
    protected string $objectId;

    /**
     * @ORM\ManyToMany(targetEntity="App\Entity\Core\Tag")
     * @ORM\JoinTable(name="tfr_includes")
     */
    protected ?DoctrineCollection $include = null;

    /**
     * @ORM\ManyToMany(targetEntity="App\Entity\Core\Tag")
     * @ORM\JoinTable(name="tfr_excludes")
     */
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
