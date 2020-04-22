<?php

declare(strict_types=1);

namespace Alchemy\AclBundle\Entity;

use Alchemy\AclBundle\Model\AccessControlEntryInterface;
use Alchemy\AclBundle\Security\PermissionInterface;
use DateTime;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Uuid;

/**
 * @ORM\Table(uniqueConstraints={@ORM\UniqueConstraint(name="uniq_ace", columns={"user_id", "object"})})
 * @ORM\Entity()
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
     * @var string
     *
     * @ORM\Column(type="string", length=36)
     */
    protected $userId;

    /**
     * The full object URI (publication:5cd05ab6-e4d8-4f2b-aa44-f0944148ae5f).
     *
     * @var string
     *
     * @ORM\Column(type="string", length=56)
     */
    protected $object;

    /**
     * @var int
     *
     * @ORM\Column(type="integer")
     */
    protected $mask = 0;

    /**
     * @var DateTime
     *
     * @ORM\Column(type="datetime")
     */
    private $createdAt;

    public function __construct()
    {
        $this->id = Uuid::uuid4();
        $this->createdAt = new DateTime();
    }

    public function getId(): string
    {
        return $this->id->__toString();
    }

    public function getUserId(): ?string
    {
        return $this->userId;
    }

    public function setUserId(string $userId): void
    {
        $this->userId = $userId;
    }

    public function getObject(): ?string
    {
        return $this->object;
    }

    public function setObject(string $object): void
    {
        $this->object = $object;
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
        for ($i = 0; $i < $length; $i++) {
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
}
