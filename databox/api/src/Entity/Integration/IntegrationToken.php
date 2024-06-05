<?php

declare(strict_types=1);

namespace App\Entity\Integration;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Link;
use App\Api\Provider\IntegrationTokenDataProvider;
use App\Entity\AbstractUuidEntity;
use App\Entity\Traits\CreatedAtTrait;
use App\Security\Voter\AbstractVoter;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Doctrine\UuidType;
use Symfony\Component\Serializer\Attribute\Groups;

#[ApiResource(
    shortName: 'integration-token',
    operations: [
        new Get(security: 'is_granted("'.AbstractVoter::READ.'", object)'),
        new Delete(security: 'is_granted("'.AbstractVoter::DELETE.'", object)'),
    ],
    normalizationContext: [
        'groups' => [self::GROUP_LIST],
    ],
)]
#[ApiResource(
    uriTemplate: '/integrations/{integrationId}/tokens',
    shortName: 'integration-token',
    operations: [
        new GetCollection(
            provider: IntegrationTokenDataProvider::class,
        ),
    ],
    uriVariables: [
        'integrationId' => new Link(
            toProperty: 'integration',
            fromClass: WorkspaceIntegration::class
        ),
    ],
    normalizationContext: [
        'groups' => [self::GROUP_LIST],
    ],
)]
#[ORM\Entity]
#[ORM\Index(columns: ['integration_id', 'user_id'], name: 'user_token')]
class IntegrationToken extends AbstractUuidEntity
{
    use CreatedAtTrait;
    final public const GROUP_LIST = 'int-token:index';

    #[ORM\ManyToOne(targetEntity: WorkspaceIntegration::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?WorkspaceIntegration $integration = null;

    #[ORM\Column(type: UuidType::NAME, nullable: true)]
    #[Groups([self::GROUP_LIST, WorkspaceIntegration::GROUP_LIST])]
    private ?string $userId;

    #[ORM\Column(type: Types::JSON, nullable: false)]
    private ?array $token;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: false)]
    #[Groups([self::GROUP_LIST, WorkspaceIntegration::GROUP_LIST])]
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

    #[Groups([self::GROUP_LIST, WorkspaceIntegration::GROUP_LIST])]
    public function isExpired(): bool
    {
        return $this->expiresAt < new \DateTimeImmutable();
    }
}
