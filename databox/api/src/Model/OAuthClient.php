<?php

declare(strict_types=1);

namespace App\Model;

use Alchemy\AuthBundle\Security\JwtUser;
use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\QueryParameter;
use ApiPlatform\OpenApi\Model\Operation as OpenApiOperation;
use App\Api\Model\Input\OAuthClientInput;
use App\Api\Processor\CreateOAuthClientProcessor;
use App\Api\Provider\OAuthClientProvider;
use Symfony\Component\Serializer\Attribute\Groups;

/**
 * A Keycloak OAuth client an integration can authorize its users with (public, standard flow).
 * Read and created with the token of the user: Keycloak applies its "realm-management" roles.
 */
#[ApiResource(
    shortName: 'oauth-client',
    operations: [
        new GetCollection(
            uriTemplate: '/oauth-clients',
            openapi: new OpenApiOperation(
                summary: 'Keycloak OAuth clients usable by an integration',
                description: 'The enabled public clients with the standard flow, the built-in clients of Keycloak excepted. The ones allowing the redirect URI of the integrations come first, then the ones whose access tokens Expose accepts ("acceptedByExpose": the client has the "expose-api" default client scope).',
            ),
            paginationEnabled: false,
            security: 'is_granted("'.JwtUser::ROLE_KEYCLOAK_VIEW_CLIENTS.'")',
            provider: OAuthClientProvider::class,
            parameters: [
                'search' => new QueryParameter(
                    schema: ['type' => 'string'],
                    description: 'Part of the client ID',
                    castToArray: false,
                ),
                'integrationId' => new QueryParameter(
                    schema: ['type' => 'string'],
                    description: 'The integration whose redirect URI is checked (any integration when omitted)',
                    castToArray: false,
                ),
            ],
        ),
        new Post(
            uriTemplate: '/oauth-clients',
            openapi: new OpenApiOperation(
                summary: 'Creates a Keycloak OAuth client for the integrations',
                description: 'A public client with the standard flow only, allowing the redirect URI of every integration.',
            ),
            security: 'is_granted("'.JwtUser::ROLE_KEYCLOAK_MANAGE_CLIENTS.'")',
            input: OAuthClientInput::class,
            validate: false,
            processor: CreateOAuthClientProcessor::class,
        ),
    ],
    normalizationContext: [
        'groups' => [self::GROUP_READ],
    ],
)]
final class OAuthClient
{
    private const string GROUP_READ = 'oauth-client:r';

    /**
     * The Keycloak ID of the client (not its client ID).
     */
    #[ApiProperty(identifier: true)]
    #[Groups(self::GROUP_READ)]
    public ?string $id = null;

    #[Groups(self::GROUP_READ)]
    public string $clientId = '';

    #[Groups(self::GROUP_READ)]
    public ?string $name = null;

    #[Groups(self::GROUP_READ)]
    public ?string $description = null;

    /**
     * @var string[]
     */
    #[Groups(self::GROUP_READ)]
    public array $redirectUris = [];

    /**
     * Whether the redirect URIs of the client allow the one of the integration.
     */
    #[Groups(self::GROUP_READ)]
    public bool $redirectUriAllowed = false;

    /**
     * Whether Expose accepts the access tokens of the client: the client has the "expose-api" client
     * scope among its default ones, which puts Expose in the audience of its tokens.
     * Only the client picker of the Expose integration tells it.
     */
    #[Groups(self::GROUP_READ)]
    public bool $acceptedByExpose = false;

    public function getId(): ?string
    {
        return $this->id;
    }
}
