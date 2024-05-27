<?php

declare(strict_types=1);

namespace App\Entity\Integration;

use App\Entity\AbstractUuidEntity;
use App\Entity\Traits\CreatedAtTrait;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Doctrine\UuidType;

#[ORM\Entity]
#[ORM\Index(columns: ['integration_id', 'user_id'], name: 'user_token')]
class IntegrationToken extends AbstractUuidEntity
{
    use CreatedAtTrait;

    #[ORM\ManyToOne(targetEntity: WorkspaceIntegration::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?WorkspaceIntegration $integration = null;

    #[ORM\Column(type: UuidType::NAME, nullable: true)]
    private ?string $userId;

    #[ORM\Column(type: Types::JSON, nullable: false)]
    private ?array $token;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: false)]
    private ?\DateTimeImmutable $expiresAt = null;

    public function getIntegration(): ?WorkspaceIntegration
    {
        return $this->integration;
    }

    public function setIntegration(?WorkspaceIntegration $integration): void
    {
        $this->integration = $integration;
    }

    public function getToken(): ?array
    {
        return $this->token;
    }

    public function setToken(?array $token): void
    {
        $this->token = $token;
    }

    public function getExpiresAt(): ?\DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function setExpiresAt(?\DateTimeImmutable $expiresAt): void
    {
        $this->expiresAt = $expiresAt;
    }

    public function getUserId(): ?string
    {
        return $this->userId;
    }

    public function setUserId(?string $userId): void
    {
        $this->userId = $userId;
    }

    public function isExpired(): bool
    {
        return $this->expiresAt < new \DateTimeImmutable();
    }
}
