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
 *  normalizationContext={"groups"={"_", "tfr:read"}},
 *  output=TagFilterRuleOutput::class,
 *  input=false,
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

    public function getInclude(): array
    {
        return $this->include;
    }

    public function setInclude(array $include): void
    {
        $this->include = $include;
    }

    public function getExclude(): array
    {
        return $this->exclude;
    }

    public function setExclude(array $exclude): void
    {
        $this->exclude = $exclude;
    }
}
