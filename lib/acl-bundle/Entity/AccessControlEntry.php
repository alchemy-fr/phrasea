<?php

declare(strict_types=1);

namespace Alchemy\AclBundle\Entity;

use Alchemy\AclBundle\Model\AccessControlEntryInterface;
use DateTime;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Uuid;

/**
 * @ORM\Table(
 *     uniqueConstraints={@ORM\UniqueConstraint(name="uniq_ace", columns={"user_type", "user_id", "object_type", "object_id"})},
 *     indexes={
 *         @ORM\Index(name="user_idx", columns={"user_type", "user_id"}),
 *         @ORM\Index(name="object_idx", columns={"object_type", "object_id"}),
 *         @ORM\Index(name="user_type_idx", columns={"user_type"}),
 *         @ORM\Index(name="object_type_idx", columns={"object_type"}),
 *     }
 * )
 * @ORM\Entity(repositoryClass="AccessControlEntryRepository")
 */
class AccessControlEntry implements AccessControlEntryInterface
{
    /**
     * @var Uuid
     *
     * @ORM\Id
     * @ORM\Column(type="uuid", unique=true)
     * @ORM\GeneratedValue(strategy="CUSTOM")
     * @ORM\CustomIdGenerator(class="Ramsey\Uuid\Doctrine\UuidGenerator")
     */
    protected $id;

    /**
     * @ORM\Column(type="smallint")
     */
    protected ?int $userType = null;

    /**
     * @ORM\Column(type="string", length=36, nullable=true)
     */
    protected ?string $userId = null;

    /**
     * The object type name (i.e. publication).
     *
     * @ORM\Column(type="string", length=20)
     */
    protected ?string $objectType = null;

    /**
     * @ORM\Column(type="uuid", nullable=true)
     */
    protected ?string $objectId = null;

    /**
     * @ORM\Column(type="integer")
     */
    protected int $mask = 0;

    /**
     * @ORM\Column(type="datetime")
     */
    private DateTime $createdAt;

    public function __construct()
    {
        $this->id = Uuid::uuid4();
        $this->createdAt = new DateTime();
    }

    public static function getUserTypeFromString(string $type): int
    {
        return self::USER_TYPES[$type];
    }

    public static function getUserTypeFromCode(int $type): string
    {
        return array_search($type, self::USER_TYPES, true);
    }

    public function getId(): string
    {
        return $this->id->__toString();
    }

    public function getUserType(): int
    {
        return $this->userType ?? self::TYPE_USER_VALUE;
    }

    public function setUserType(int $userType): void
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

    public function getObjectType(): ?string
    {
        return $this->objectType;
    }

    public function setObjectType(string $objectType): void
    {
        $this->objectType = $objectType;
    }

    public function getObjectId(): ?string
    {
        return $this->objectId;
    }

    public function setObjectId(?string $objectId): void
    {
        $this->objectId = $objectId;
    }

    public function getMask(): int
    {
        return $this->mask;
    }

    public function setMask(int $mask): void
    {
        $this->mask = $mask;
    }

    public function setPermissions(array $permissions): void
    {
        foreach ($permissions as $permission) {
            $this->addPermission($permission);
        }
    }

    public function getPermissions(): array
    {
        $permissions = [];
        $length = 30;
        for ($i = 0; $i < $length; ++$i) {
            $bit = 1 << $i;

            if (($bit & $this->mask) === $bit) {
                $permissions[] = $bit;
            }
        }

        return $permissions;
    }

    public function hasPermission(int $permission): bool
    {
        return ($this->mask & $permission) === $permission;
    }

    public function addPermission(int $permission): void
    {
        $this->mask |= $permission;
    }

    public function removePermission(int $permission): void
    {
        $this->mask &= ~$permission;
    }

    public function resetPermissions(): void
    {
        $this->mask = 0;
    }

    public function getCreatedAt(): DateTime
    {
        return $this->createdAt;
    }

    public function getUserTypeString(): string
    {
        return self::getUserTypeFromCode($this->userType);
    }

    public function setUserTypeString(string $type): void
    {
        $this->setUserType(self::getUserTypeFromString($type));
    }
}
