<?php

declare(strict_types=1);

namespace App\Api\Processor;

use Alchemy\AuthBundle\Client\KeycloakClient;
use Alchemy\AuthBundle\Exception\ClientAlreadyExistsException;
use Alchemy\AuthBundle\Security\JwtUser;
use Alchemy\AuthBundle\Security\Traits\KeycloakTokenOwnerTrait;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use ApiPlatform\Validator\Exception\ValidationException;
use ApiPlatform\Validator\ValidatorInterface;
use App\Api\Model\Input\OAuthClientInput;
use App\Api\Provider\OAuthClientProvider;
use App\Integration\Auth\OAuthRedirectUriMatcher;
use App\Model\OAuthClient;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;

/**
 * Creates the Keycloak client of the integrations authorizing their users: public, with the
 * standard flow only, allowing the redirect URI of every integration (so that it can be
 * created before the integration and shared by several ones).
 */
final class CreateOAuthClientProcessor implements ProcessorInterface
{
    use KeycloakTokenOwnerTrait;

    /**
     * Informative attribute of the created clients.
     */
    final public const string CREATED_BY_ATTRIBUTE = 'phrasea.created_by';

    public function __construct(
        private readonly KeycloakClient $keycloakClient,
        private readonly OAuthRedirectUriMatcher $redirectUriMatcher,
        private readonly ValidatorInterface $validator,
    ) {
    }

    /**
     * @param OAuthClientInput $data
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): OAuthClient
    {
        $user = $this->getKeycloakTokenOwner(JwtUser::ROLE_KEYCLOAK_MANAGE_CLIENTS);
        $this->validator->validate($data);

        $clientId = (string) $data->clientId;
        $name = trim((string) $data->name);
        $description = trim((string) $data->description);

        try {
            $created = $this->keycloakClient->createClient([
                'clientId' => $clientId,
                'name' => '' !== $name ? $name : $clientId,
                'description' => $description,
                'enabled' => true,
                'protocol' => 'openid-connect',
                'publicClient' => true,
                'bearerOnly' => false,
                'standardFlowEnabled' => true,
                'implicitFlowEnabled' => false,
                'directAccessGrantsEnabled' => false,
                'serviceAccountsEnabled' => false,
                'rootUrl' => $this->redirectUriMatcher->getClientRootUrl(),
                'redirectUris' => [$this->redirectUriMatcher->getClientRedirectUri()],
                'attributes' => [
                    self::CREATED_BY_ATTRIBUTE => 'databox',
                ],
            ], $user->getJwt());
        } catch (ClientAlreadyExistsException $e) {
            $message = 'A client with this ID already exists.';

            throw new ValidationException(new ConstraintViolationList([new ConstraintViolation($message, $message, [], $data, 'clientId', $clientId)]), previous: $e);
        }

        return OAuthClientProvider::createClient($created, $this->redirectUriMatcher->getRedirectUri());
    }
}
