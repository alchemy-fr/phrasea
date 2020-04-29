<?php

declare(strict_types=1);

namespace App\Entity;

use Arthem\Bundle\LocaleBundle\Model\UserLocaleInterface;
use DateTime;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Uuid;
use Symfony\Component\Security\Core\User\EquatableInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * @ORM\Entity(repositoryClass="UserRepository")
 * @ORM\Table(name="`user`")
 * @ORM\EntityListeners({"App\Doctrine\Listener\UserDeleteListener"})
 */
class User implements UserInterface, UserLocaleInterface, EquatableInterface
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
     * @ORM\Column(type="string", length=255, unique=true)
     */
    protected $username;

    /**
     * @var bool
     * @ORM\Column(type="boolean")
     */
    protected $emailVerified = false;

    /**
     * @var bool
     * @ORM\Column(type="boolean")
     */
    protected $enabled;

    /**
     * @var string|null
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    protected $securityToken;

    /**
     * @var string
     * @ORM\Column(type="string", length=255)
     */
    protected $salt;

    /**
     * @var array
     * @ORM\Column(type="json_array")
     */
    protected $roles = [];

    /**
     * @var string|null
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    protected $password;

    /**
     * @var string|null
     * @ORM\Column(type="string", length=5, nullable=true)
     */
    protected $locale;

    /**
     * @var string
     */
    protected $plainPassword;

    /**
     * @var DateTime
     *
     * @ORM\Column(type="datetime")
     */
    private $createdAt;

    /**
     * @var DateTime
     *
     * @ORM\Column(type="datetime", nullable=true)
     */
    private $lastInviteAt;

    /**
     * @var DateTime|null
     *
     * @ORM\Column(type="datetime", nullable=true)
     */
    private $updatedAt;

    /**
     * Not mapped.
     *
     * @var bool
     */
    protected $inviteByEmail = false;

    /**
     * @var Group[]|Collection
     *
     * @ORM\ManyToMany(targetEntity="Group", inversedBy="users")
     */
    protected $groups;

    public function __construct()
    {
        $this->createdAt = new DateTime();
        $this->groups = new ArrayCollection();
    }

    public function getId(): string
    {
        if (null === $this->id) {
            return '';
        }

        return $this->id->__toString();
    }

    public function getUsername()
    {
        return $this->username;
    }

    public function getEmail(): ?string
    {
        return $this->getUsername();
    }

    public function setEmail(string $email): void
    {
        $this->setUsername($email);
    }

    public function setUsername(string $username): void
    {
        $this->username = $username;
    }

    public function getRoles(): array
    {
        return $this->roles;
    }

    public function setRoles(array $roles): void
    {
        $this->roles = $roles;
    }

    public function getPassword()
    {
        return $this->password;
    }

    public function hasPassword(): bool
    {
        return null !== $this->password;
    }

    public function getSalt()
    {
        return $this->salt;
    }

    public function eraseCredentials()
    {
        $this->plainPassword = null;
    }

    public function setSalt(string $salt): void
    {
        $this->salt = $salt;
    }

    public function setPassword(string $password): void
    {
        $this->password = $password;
    }

    public function getPlainPassword(): ?string
    {
        return $this->plainPassword;
    }

    public function setPlainPassword(?string $plainPassword): void
    {
        $this->plainPassword = $plainPassword;
    }

    public function getCreatedAt(): DateTime
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): DateTime
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(DateTime $updatedAt): void
    {
        $this->updatedAt = $updatedAt;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function setEnabled(bool $enabled): void
    {
        $this->enabled = $enabled;
    }

    public function getSecurityToken(): ?string
    {
        return $this->securityToken;
    }

    public function setSecurityToken(?string $securityToken): void
    {
        $this->securityToken = $securityToken;
    }

    public function isEmailVerified(): bool
    {
        return $this->emailVerified;
    }

    public function setEmailVerified(bool $emailVerified): void
    {
        $this->emailVerified = $emailVerified;
    }

    public function isInviteByEmail(): bool
    {
        return $this->inviteByEmail;
    }

    public function setInviteByEmail(bool $inviteByEmail): void
    {
        $this->inviteByEmail = $inviteByEmail;
    }

    public function getLastInviteAt(): DateTime
    {
        return $this->lastInviteAt;
    }

    public function setLastInviteAt(DateTime $lastInviteAt): void
    {
        $this->lastInviteAt = $lastInviteAt;
    }

    public function canBeInvited(int $allowedDelay): bool
    {
        return null === $this->lastInviteAt
            || $this->lastInviteAt->getTimestamp() < time() - $allowedDelay;
    }

    public function getLocale(): ?string
    {
        return $this->locale;
    }

    public function setLocale(?string $locale): void
    {
        $this->locale = $locale;
    }

    /**
     * @return Group[]
     */
    public function getGroups(): Collection
    {
        return $this->groups;
    }

    /**
     * Return groups indexed by id
     */
    public function getIndexedGroups(): array
    {
        $groups = [];
        foreach ($this->getGroups() as $group) {
            $groups[$group->getId()] = $group->getName();
        }
        return $groups;
    }

    public function addGroup(Group $group): void
    {
        $group->addUser($this);
        $this->groups->add($group);
    }

    public function removeGroup(Group $group): void
    {
        $group->removeUser($this);
        $this->groups->removeElement($group);
    }

    public function isEqualTo(UserInterface $user)
    {
        if (!$user instanceof User
            || $this->getId() !== $user->getId()) {
            return false;
        }

        return count($this->getRoles()) === count($user->getRoles()) && empty(array_diff($this->getRoles(), $user->getRoles()));
    }
}
