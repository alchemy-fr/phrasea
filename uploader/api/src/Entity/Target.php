<?php

declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Core\Annotation\ApiProperty;
use DateTimeImmutable;
use DateTimeInterface;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Uuid;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @ORM\Entity
 * @ORM\Table
 */
class Target
{
    /**
     * @ApiProperty(identifier=true)
     * @Groups({"target:index"})
     *
     * @var Uuid
     *
     * @ORM\Id
     * @ORM\Column(type="uuid", unique=true)
     */
    private $id;

    /**
     * @ORM\Column(type="string", length=1000)
     * @Assert\Length(max=1000)
     * @Assert\NotBlank
     * @Groups({"target:index"})
     */
    private ?string $name = null;

    /**
     * @ORM\Column(type="string", length=255)
     * @Assert\Length(max=255)
     * @Assert\Url()
     * @Assert\NotBlank
     * @Groups({"target:index"})
     */
    private ?string $targetUrl = null;

    /**
     * @ORM\Column(type="string", length=2000, nullable=true)
     * @Assert\Length(max=2000)
     * @Groups({"target:index"})
     */
    private ?string $targetAccessToken = null;

    /**
     * Null value allows everyone.
     *
     * @ORM\Column(type="json", nullable=true)
     * @Groups({"target:index"})
     */
    private ?array $allowedGroups = null;

    /**
     * @ORM\Column(type="datetime")
     * @Groups({"target:index"})
     */
    private DateTimeInterface $createdAt;

    public function __construct()
    {
        $this->createdAt = new DateTimeImmutable();
        $this->id = Uuid::uuid4();
    }

    public function getId()
    {
        return $this->id->__toString();
    }

    public function getCreatedAt()
    {
        return $this->createdAt;
    }

    public function getFormSchemas(): Collection
    {
        return $this->formSchemas;
    }

    public function getTargetAccessToken(): ?string
    {
        return $this->targetAccessToken;
    }

    public function setTargetAccessToken(?string $targetAccessToken): void
    {
        $this->targetAccessToken = $targetAccessToken;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): void
    {
        $this->name = $name;
    }

    public function getTargetUrl(): ?string
    {
        return $this->targetUrl;
    }

    public function setTargetUrl(?string $targetUrl): void
    {
        $this->targetUrl = $targetUrl;
    }

    public function getAllowedGroups(): ?array
    {
        return $this->allowedGroups;
    }

    public function setAllowedGroups(?array $allowedGroups): void
    {
        $this->allowedGroups = $allowedGroups;
    }

    public function __toString()
    {
        return $this->getName() ?? $this->getId();
    }
}
