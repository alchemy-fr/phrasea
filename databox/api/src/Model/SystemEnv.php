<?php

declare(strict_types=1);

namespace App\Model;

use Alchemy\AuthBundle\Security\JwtUser;
use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\QueryParameter;
use App\Api\Provider\SystemEnvCollectionProvider;
use Symfony\Component\Serializer\Attribute\Groups;

/**
 * A `PWI_<NAME>` environment variable of the instance, that `${NAME}` resolves to
 * in the configuration of the integrations when no secret nor variable of the
 * scope has this name. Listed by name only, without the prefix: its value is
 * never returned.
 */
#[ApiResource(
    shortName: 'system-env',
    operations: [
        new GetCollection(
            uriTemplate: '/system-envs',
            paginationEnabled: false,
            parameters: [
                'workspace' => new QueryParameter(
                    schema: ['type' => 'string'],
                    description: 'Workspace ID or IRI ("workspace" or "global" is required)',
                    castToArray: false,
                ),
                'global' => new QueryParameter(
                    schema: ['type' => 'boolean'],
                    description: 'For the instance integrations (administrators only)',
                    castToNativeType: true,
                    castToArray: false,
                ),
            ],
        ),
    ],
    normalizationContext: [
        'groups' => [self::GROUP_READ],
    ],
    security: 'is_granted("'.JwtUser::IS_AUTHENTICATED_FULLY.'")',
    provider: SystemEnvCollectionProvider::class,
)]
final class SystemEnv
{
    private const string GROUP_READ = 'sys-env:r';

    public function __construct(
        #[ApiProperty(identifier: true)]
        #[Groups(self::GROUP_READ)]
        public string $name,
    ) {
    }
}
