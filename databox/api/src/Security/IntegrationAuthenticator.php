<?php

declare(strict_types=1);

namespace App\Security;

use Alchemy\AuthBundle\Security\RoleMapper;
use ApiPlatform\Metadata\Exception\OperationNotFoundException;
use ApiPlatform\Metadata\Exception\ResourceClassNotFoundException;
use ApiPlatform\Metadata\Resource\Factory\ResourceMetadataCollectionFactoryInterface;
use App\Integration\Phrasea\Uploader\UploaderIntegrationKeyChecker;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;

/**
 * Authenticates an Uploader integration calling the API on its own, where nobody can be called on behalf of
 * (the forms filled through an upload link): `Authorization: ApiKey <integration ID>:<security key of the integration>`.
 * It then acts as the owner of the integration, with their roles and groups (as an impersonation would, see IntegrationOwnerProvider).
 *
 * Only the API Platform operations flagged with OPERATION_ACCESS in their extraProperties accept it,
 * any other one is refused.
 */
final class IntegrationAuthenticator extends AbstractAuthenticator
{
    final public const string OPERATION_ACCESS = 'integration_access';

    /**
     * The integration ID is a UUID: the key is whatever follows its first colon.
     */
    private const string AUTHORIZATION_PATTERN = '#^ApiKey\s+([0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}):(.+)$#i';

    public function __construct(
        private readonly UploaderIntegrationKeyChecker $keyChecker,
        private readonly ResourceMetadataCollectionFactoryInterface $resourceMetadataCollectionFactory,
        private readonly IntegrationOwnerProvider $ownerProvider,
        private readonly RoleMapper $roleMapper,
    ) {
    }

    public function supports(Request $request): bool
    {
        // The other routes (the incoming commits of the Uploader) check their key themselves
        return $request->attributes->has('_api_resource_class')
            && null !== self::parseAuthorization($request);
    }

    public function authenticate(Request $request): Passport
    {
        if (!$this->isOperationOpenToIntegrations($request)) {
            throw new AccessDeniedHttpException('An integration key is not accepted on this operation');
        }

        [$integrationId, $key] = self::parseAuthorization($request) ?? throw new \LogicException('Unsupported request');
        $config = $this->keyChecker->checkKey($integrationId, $key);
        $ownerId = $config->getWorkspaceIntegration()->getOwnerId() ?? throw new AccessDeniedHttpException('The integration has no owner');
        $owner = $this->ownerProvider->getOwner($ownerId);
        $user = new IntegrationUser(
            $config->getIntegrationId(),
            $config->getWorkspaceId(),
            $ownerId,
            $owner['username'],
            $this->roleMapper->getRoles($owner['roles']),
            $owner['groups'],
        );

        return new SelfValidatingPassport(new UserBadge($user->getUserIdentifier(), static fn (): IntegrationUser => $user));
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        return null;
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        return new JsonResponse([
            'message' => strtr($exception->getMessageKey(), $exception->getMessageData()),
        ], Response::HTTP_UNAUTHORIZED);
    }

    /**
     * @return array{0: string, 1: string}|null the integration ID and the key
     */
    private static function parseAuthorization(Request $request): ?array
    {
        if (!preg_match(self::AUTHORIZATION_PATTERN, (string) $request->headers->get('Authorization', ''), $matches)) {
            return null;
        }

        return [strtolower($matches[1]), $matches[2]];
    }

    private function isOperationOpenToIntegrations(Request $request): bool
    {
        $resourceClass = $request->attributes->get('_api_resource_class');
        $operationName = $request->attributes->get('_api_operation_name');
        if (!\is_string($resourceClass) || !\is_string($operationName) || !$request->isMethodSafe()) {
            return false;
        }

        try {
            $operation = $this->resourceMetadataCollectionFactory->create($resourceClass)->getOperation($operationName);
        } catch (OperationNotFoundException|ResourceClassNotFoundException) {
            return false;
        }

        return true === ($operation->getExtraProperties()[self::OPERATION_ACCESS] ?? false);
    }
}
