<?php

declare(strict_types=1);

namespace App\Entity\Core;

use ApiPlatform\Core\Annotation\ApiFilter;
use App\Entity\AbstractUuidEntity;
use App\Entity\Traits\CreatedAtTrait;
use App\Entity\Traits\UpdatedAtTrait;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection as DoctrineCollection;
use Doctrine\ORM\Mapping as ORM;
use ApiPlatform\Core\Bridge\Doctrine\Orm\Filter\SearchFilter;

/**
 * @ORM\Table(
 *     uniqueConstraints={@ORM\UniqueConstraint(name="rend_uniq_rule", columns={"user_type", "user_id", "object_type", "object_id"})},
 *     indexes={
 *         @ORM\Index(name="rr_user_idx", columns={"user_type", "user_id"}),
 *         @ORM\Index(name="rr_object_idx", columns={"object_type", "object_id"}),
 *         @ORM\Index(name="rr_user_type_idx", columns={"user_type"}),
 *     }
 * )
 * @ORM\Entity(repositoryClass="App\Repository\Core\RenditionRuleRepository")
 * @ApiFilter(SearchFilter::class, properties={"allowed"="exact", "userType"="exact", "userId"="exact", "objectType"="exact", "objectId"="exact"})
 */
class RenditionRule extends AbstractUuidEntity
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
     * @var RenditionClass[]|Collection
     * @ORM\ManyToMany(targetEntity="RenditionClass")
     * @ORM\JoinTable(name="sdr_allowed")
     */
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
