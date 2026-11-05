<?php

declare(strict_types=1);

namespace App\Api\Model\Output;

use ApiPlatform\Metadata\ApiProperty;
use App\Entity\Log\AuditLog;
use Symfony\Component\Serializer\Attribute\Groups;

final class AuditLogOutput extends AbstractUuidOutput
{
    #[Groups([AuditLog::GROUP_READ])]
    public ?\DateTimeImmutable $date = null;

    /**
     * The action key, e.g. "asset_moved".
     */
    #[ApiProperty(jsonSchemaContext: ['type' => 'string'])]
    #[Groups([AuditLog::GROUP_READ])]
    public ?string $action = null;

    #[ApiProperty(jsonSchemaContext: [
        'type' => 'string',
        'enum' => ['user', 'oauth_client', 'system'],
    ])]
    #[Groups([AuditLog::GROUP_READ])]
    public ?string $actorType = null;

    /**
     * The acting user, with the username they had then.
     *
     * @var array{id: string, username: ?string}|null
     */
    #[Groups([AuditLog::GROUP_READ])]
    public ?array $user = null;

    #[Groups([AuditLog::GROUP_READ])]
    public ?string $impersonatorId = null;

    #[Groups([AuditLog::GROUP_READ])]
    public ?string $clientId = null;

    #[Groups([AuditLog::GROUP_READ])]
    public ?string $ip = null;

    /**
     * The object type key, e.g. "asset", "saved_search".
     */
    #[Groups([AuditLog::GROUP_READ])]
    public ?string $objectType = null;

    #[Groups([AuditLog::GROUP_READ])]
    public ?string $objectId = null;

    /**
     * The title or name of the object when the action was logged.
     */
    #[Groups([AuditLog::GROUP_READ])]
    public ?string $objectLabel = null;

    #[Groups([AuditLog::GROUP_READ])]
    public ?string $workspaceId = null;

    /**
     * Shared by an action and its asynchronous effects.
     */
    #[Groups([AuditLog::GROUP_READ])]
    public ?string $correlationId = null;

    #[Groups([AuditLog::GROUP_READ])]
    public array $data = [];

    /**
     * The origin of the action: "uri", "_route", "source" (api, admin, console, worker, automation, integration), "command".
     */
    #[Groups([AuditLog::GROUP_READ])]
    public array $meta = [];
}
