<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Doctrine\UuidGenerator;
use Ramsey\Uuid\Doctrine\UuidType;
use Ramsey\Uuid\Uuid;

#[ORM\Entity]
class Contact implements \Stringable
{
    /**
     * @var string
     */
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: UuidGenerator::class)]
    protected $id;

    /**
     * @var string
     */
    #[ORM\Column(type: Types::STRING, length: 128, unique: true, nullable: true)]
    protected $userId;

    /**
     * @var string|null
     */
    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    protected $email;

    /**
     * @var string|null
     */
    #[ORM\Column(type: Types::STRING, length: 20, nullable: true)]
    protected $phone;

    /**
     * @var string|null
     */
    #[ORM\Column(type: Types::STRING, length: 5, nullable: true)]
    protected $locale;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private readonly \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->id = Uuid::uuid4();
    }

    public function getId(): string
    {
        return (string) $this->id;
    }

    public function setUserId(?string $userId): void
    {
        $this->userId = $userId;
    }

    public function getUserId(): ?string
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

    public function getPhone(): ?string
    {
        return $this->phone;
    }

    public function setPhone(?string $phone): void
    {
        $this->phone = $phone;
    }

    public function getLocale(): ?string
    {
        return $this->locale;
    }

    public function setLocale(?string $locale): void
    {
        $this->locale = $locale;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function __toString(): string
    {
        return sprintf('%s %s', $this->getEmail(), $this->getUserId());
    }
}
