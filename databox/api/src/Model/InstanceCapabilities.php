<?php

declare(strict_types=1);

namespace App\Model;

use Alchemy\AuthBundle\Security\JwtUser;
use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\OpenApi\Model\Operation as OpenApiOperation;
use App\Api\Provider\InstanceCapabilitiesProvider;

/**
 * The instance capabilities of the current user (see App\Security\InstancePermission),
 * answered as a plain JSON object: {"createWorkspace": bool, "createProfile": bool, …}.
 */
#[ApiResource(
    shortName: 'capabilities',
    operations: [
        new Get(
            uriTemplate: '/capabilities',
            openapi: new OpenApiOperation(
                summary: 'Instance capabilities of the current user',
                description: 'Capabilities granted outside any workspace: creating a workspace or a profile, sharing its baskets, publishing its saved searches.',
            ),
            security: 'is_granted("'.JwtUser::IS_AUTHENTICATED_FULLY.'")',
            name: 'instance_capabilities',
            provider: InstanceCapabilitiesProvider::class,
        ),
    ],
)]
final class InstanceCapabilities
{
    #[ApiProperty(identifier: true)]
    public string $id = 'capabilities';
}
