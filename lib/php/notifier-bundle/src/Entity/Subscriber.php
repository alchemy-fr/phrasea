<?php

declare(strict_types=1);

namespace Alchemy\NotifierBundle\Entity;

use Alchemy\CoreBundle\Entity\AbstractUuidEntity;
use Alchemy\CoreBundle\Entity\Traits\CreatedAtTrait;
use Alchemy\CoreBundle\Entity\Traits\UpdatedAtTrait;
use Alchemy\NotifierBundle\Repository\SubscriberRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * A recipient of notifications, uniquely identified by its userId.
 */
#[ORM\Entity(repositoryClass: SubscriberRepository::class)]
#[ORM\Table(name: 'notifier_subscriber')]
#[ORM\UniqueConstraint(name: 'uniq_notifier_subscriber_user', fields: ['userId'])]
class Subscriber extends AbstractUuidEntity
{
    use CreatedAtTrait;
    use UpdatedAtTrait;

    #[ORM\Column(type: Types::GUID, unique: true)]
    private string $userId;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $email = null;

    #[ORM\Column(type: Types::STRING, length: 30, nullable: true)]
    private ?string $phoneNumber = null;

    #[ORM\Column(type: Types::STRING, length: 10, nullable: true)]
    private ?string $locale = null;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $displayName = null;

    public function __construct(string $userId)
    {
        parent::__construct();
        $this->userId = $userId;
    }

    public function getUserId(): string
    {
        return $this->userId;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(?string $email): void
    {
        $this->email = $email;
    }

    public function getPhoneNumber(): ?string
    {
        return $this->phoneNumber;
    }

    public function setPhoneNumber(?string $phoneNumber): void
    {
        $this->phoneNumber = $phoneNumber;
    }

    public function getLocale(): ?string
    {
        return $this->locale;
    }

    public function setLocale(?string $locale): void
    {
        $this->locale = $locale;
    }

    public function getDisplayName(): ?string
    {
        return $this->displayName;
    }

    public function setDisplayName(?string $displayName): void
    {
        $this->displayName = $displayName;
    }
}
