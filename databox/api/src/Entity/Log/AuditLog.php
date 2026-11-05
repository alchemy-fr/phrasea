<?php

declare(strict_types=1);

namespace App\Entity\Log;

use Alchemy\AuthBundle\Security\JwtUser;
use Alchemy\TrackBundle\Entity\AbstractLog;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\QueryParameter;
use App\Api\Model\Output\AuditLogOutput;
use App\Api\Provider\AuditLogCollectionProvider;
use App\Model\AuditActorTypeEnum;
use App\Model\AuditLogActionEnum;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Doctrine\UuidType;
use Ramsey\Uuid\UuidInterface;

#[ApiResource(
    shortName: 'audit-log',
    operations: [
        new Get(),
        new GetCollection(
            parameters: [
                'action' => new QueryParameter(
                    schema: ['type' => 'string'],
                    description: 'Action key(s), e.g. "asset_moved"',
                ),
                'action[]' => new QueryParameter(property: 'action', openApi: false),
                'actorType' => new QueryParameter(
                    schema: ['type' => 'string', 'enum' => ['user', 'oauth_client', 'system']],
                    castToArray: false,
                ),
                'objectType' => new QueryParameter(
                    schema: ['type' => 'string'],
                    description: 'Object type key, e.g. "asset", "saved_search"',
                    castToArray: false,
                ),
                'objectId' => new QueryParameter(schema: ['type' => 'string'], castToArray: false),
                'userId' => new QueryParameter(
                    schema: ['type' => 'string'],
                    description: 'The acting user, or the admin impersonating them',
                    castToArray: false,
                ),
                'clientId' => new QueryParameter(schema: ['type' => 'string'], castToArray: false),
                'workspaceId' => new QueryParameter(schema: ['type' => 'string'], castToArray: false),
                'correlationId' => new QueryParameter(schema: ['type' => 'string'], castToArray: false),
                'date[after]' => new QueryParameter(
                    schema: ['type' => 'string', 'format' => 'date-time'],
                    castToArray: false,
                ),
                'date[before]' => new QueryParameter(
                    schema: ['type' => 'string', 'format' => 'date-time'],
                    castToArray: false,
                ),
                'limit' => new QueryParameter(schema: ['type' => 'integer'], castToArray: false),
                'page' => new QueryParameter(schema: ['type' => 'integer'], castToArray: false),
            ],
            provider: AuditLogCollectionProvider::class,
        ),
    ],
    normalizationContext: [
        'groups' => [self::GROUP_READ],
    ],
    output: AuditLogOutput::class,
    order: ['date' => 'DESC', 'id' => 'DESC'],
    security: 'is_granted("'.JwtUser::ROLE_ADMIN.'")',
)]
#[ORM\Entity]
#[ORM\Index(columns: ['date'])]
#[ORM\Index(columns: ['user_id'])]
#[ORM\Index(columns: ['impersonator_id'])]
#[ORM\Index(columns: ['action'])]
#[ORM\Index(columns: ['object_type', 'object_id'])]
#[ORM\Index(columns: ['workspace_id'])]
#[ORM\Index(columns: ['correlation_id'])]
class AuditLog extends AbstractLog
{
    final public const string GROUP_READ = 'audit-log:r';

    #[ORM\Column(type: Types::INTEGER, nullable: false, enumType: AuditLogActionEnum::class)]
    private ?AuditLogActionEnum $action = null;

    #[ORM\Column(type: Types::SMALLINT, nullable: false, enumType: AuditActorTypeEnum::class)]
    private AuditActorTypeEnum $actorType = AuditActorTypeEnum::System;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $clientId = null;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $userName = null;

    #[ORM\Column(type: Types::SMALLINT, nullable: true)]
    private ?int $objectType = null;

    #[ORM\Column(type: Types::STRING, length: 36, nullable: true)]
    private ?string $objectId = null;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $objectLabel = null;

    #[ORM\Column(type: UuidType::NAME, nullable: true)]
    private UuidInterface|string|null $workspaceId = null;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $impersonatorId = null;

    #[ORM\Column(type: UuidType::NAME, nullable: true)]
    private UuidInterface|string|null $correlationId = null;

    #[ORM\Column(type: Types::JSON, nullable: false)]
    protected array $data = [];

    public function getAction(): AuditLogActionEnum
    {
        return $this->action;
    }

    public function setAction(AuditLogActionEnum $action): void
    {
        $this->action = $action;
    }

    public function getActorType(): AuditActorTypeEnum
    {
        return $this->actorType;
    }

    public function setActorType(AuditActorTypeEnum $actorType): void
    {
        $this->actorType = $actorType;
    }

    public function getClientId(): ?string
    {
        return $this->clientId;
    }

    public function setClientId(?string $clientId): void
    {
        $this->clientId = $clientId;
    }

    public function getUserName(): ?string
    {
        return $this->userName;
    }

    public function setUserName(?string $userName): void
    {
        $this->userName = null !== $userName ? mb_substr($userName, 0, 255) : null;
    }

    public function getObjectType(): ?int
    {
        return $this->objectType;
    }

    public function setObjectType(?int $objectType): void
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

    public function getObjectLabel(): ?string
    {
        return $this->objectLabel;
    }

    public function setObjectLabel(?string $objectLabel): void
    {
        $this->objectLabel = null !== $objectLabel ? mb_substr($objectLabel, 0, 255) : null;
    }

    public function getWorkspaceId(): ?string
    {
        return null !== $this->workspaceId ? (string) $this->workspaceId : null;
    }

    public function setWorkspaceId(?string $workspaceId): void
    {
        $this->workspaceId = $workspaceId;
    }

    public function getImpersonatorId(): ?string
    {
        return $this->impersonatorId;
    }

    public function setImpersonatorId(?string $impersonatorId): void
    {
        $this->impersonatorId = $impersonatorId;
    }

    public function getCorrelationId(): ?string
    {
        return null !== $this->correlationId ? (string) $this->correlationId : null;
    }

    public function setCorrelationId(?string $correlationId): void
    {
        $this->correlationId = $correlationId;
    }

    public function getData(): array
    {
        return $this->data;
    }

    public function setData(array $data): void
    {
        $this->data = $data;
    }
}
