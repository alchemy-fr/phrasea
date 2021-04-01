<?php

declare(strict_types=1);

namespace App\Entity;

use DateTime;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Uuid;

/**
 * @ORM\Entity()
 * @ORM\Table(name="`group`")
 */
class Group
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
     * @ORM\Column(type="string", length=150, unique=true)
     */
    protected ?string $name = null;

    /**
     * @ORM\Column(type="json_array")
     */
    protected array $roles = [];

    /**
     * @var User[]|Collection
     *
     * @ORM\ManyToMany(targetEntity="User", mappedBy="groups")
     */
    protected $users;

    /**
     * @ORM\Column(type="datetime")
     */
    private DateTime $createdAt;

    public function __construct()
    {
        $this->createdAt = new DateTime();
        $this->users = new ArrayCollection();
    }

    public function getId(): string
    {
        if (null === $this->id) {
            return '';
        }

        return $this->id->__toString();
    }

    public function setId($id): void
    {
        if ($id instanceof Uuid) {
            $this->id = $id;
        } else {
            $this->id = Uuid::fromString($id);
        }
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getCreatedAt(): DateTime
    {
        return $this->createdAt;
    }

    public function addUser(User $user): void
    {
        $this->users->add($user);
    }

    public function removeUser(User $user): void
    {
        $this->users->removeElement($user);
    }

    public function getUserCount(): int
    {
        return $this->users->count();
    }

    public function getRoles(): array
    {
        return $this->roles;
    }

    public function setRoles(array $roles): void
    {
        $this->roles = $roles;
    }

    public function __toString()
    {
        return $this->getName() ?? '';
    }
}
