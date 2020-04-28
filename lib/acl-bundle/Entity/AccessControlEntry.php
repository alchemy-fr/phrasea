<?php

declare(strict_types=1);

namespace Alchemy\AclBundle\Entity;

use Alchemy\AclBundle\Model\AccessControlEntryInterface;
use DateTime;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Uuid;

/**
 * @ORM\Table(uniqueConstraints={@ORM\UniqueConstraint(name="uniq_ace", columns={"entity_type", "entity_id", "object"})})
 * @ORM\Entity()
 */
class AccessControlEntry implements AccessControlEntryInterface
{
    const ENTITY_USER = 0;
    const ENTITY_GROUP = 1;

    const ENTITY_TYPES = [
        'user' => self::ENTITY_USER,
        'group' => self::ENTITY_GROUP,
    ];

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
    protected int $entityType;

    /**
     * @ORM\Column(type="string", length=36)
     */
    protected string $entityId;

    /**
     * The full object URI (publication:5cd05ab6-e4d8-4f2b-aa44-f0944148ae5f).
     *
     * @ORM\Column(type="string", length=56)
     */
    protected string $object;

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

    public static function getEntityTypeFromString(string $type): int
    {
        return self::ENTITY_TYPES[$type];
    }

    public static function getEntityTypeFromCode(int $type): string
    {
        return array_search($type, self::ENTITY_TYPES, true);
    }

    public function getId(): string
    {
        return $this->id->__toString();
    }

    public function getEntityId(): ?string
    {
        return $this->entityId;
    }

    public function setEntityId(string $entityId): void
    {
        $this->entityId = $entityId;
    }

    public function getEntityType(): int
    {
        return $this->entityType;
    }

    public function setEntityType(int $entityType): void
    {
        $this->entityType = $entityType;
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

    public function getEntityTypeString(): string
    {
        return self::getEntityTypeFromCode($this->entityType);
    }

    public function setEntityTypeString(string $type): void
    {
        $this->setEntityType(self::getEntityTypeFromString($type));
    }
}
