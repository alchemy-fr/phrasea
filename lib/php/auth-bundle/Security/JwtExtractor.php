<?php

declare(strict_types=1);

namespace Alchemy\AuthBundle\Security;

use Lcobucci\JWT\Encoding\CannotDecodeContent;
use Lcobucci\JWT\Encoding\JoseEncoder;
use Lcobucci\JWT\Token\InvalidTokenStructure;
use Lcobucci\JWT\Token\Parser;
use Lcobucci\JWT\UnencryptedToken;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

final readonly class JwtExtractor
{
    /**
     * Roles of the Keycloak "realm-management" client: composite roles (e.g. "realm-admin")
     * are expanded in the token.
     */
    final public const string KEYCLOAK_ROLE_VIEW_CLIENTS = 'view-clients';
    final public const string KEYCLOAK_ROLE_MANAGE_CLIENTS = 'manage-clients';

    private Parser $parser;

    public function __construct(
        private RoleMapper $roleMapper,
        private array $requiredRoles,
        private LoggerInterface $logger,
    ) {
        $this->parser = new Parser(new JoseEncoder());
    }

    public function parseJwt(string $jwt): UnencryptedToken
    {
        try {
            $token = $this->parser->parse($jwt);
        } catch (InvalidTokenStructure|CannotDecodeContent) {
            throw new UnauthorizedHttpException('Invalid token');
        }

        if (!$token instanceof UnencryptedToken) {
            throw new \InvalidArgumentException(sprintf('Token is not a %s', UnencryptedToken::class));
        }

        return $token;
    }

    public function getUserFromToken(UnencryptedToken $token): JwtUser|JwtOauthClient
    {
        $claims = $token->claims();

        $scope = $claims->get('scope', '');

        $scopes = !empty($scope) ? explode(' ', $scope) : [];

        if (!empty($clientId = $claims->get('client_id'))) {
            return new JwtOauthClient(
                $token->toString(),
                $clientId,
                $scopes,
            );
        }

        $username = $claims->get('preferred_username');
        $sub = $claims->get('sub');

        if (empty($username)) {
            $this->logger->error('Missing "preferred_username" from Keycloak, using sub as username instead', [
                'sub' => $sub,
                'claim_keys' => array_keys($claims->all()),
            ]);
            $username = $sub;
        }

        $idpRoles = $claims->get('roles', []);
        if (!empty($this->requiredRoles)) {
            foreach ($this->requiredRoles as $requiredRole) {
                if (!in_array($requiredRole, $idpRoles, true)) {
                    throw new AccessDeniedHttpException('Missing required role: '.$requiredRole);
                }
            }
        }

        $roles = $this->roleMapper->getRoles($idpRoles);
        $clientRoles = $claims->get('resource_access', [])[Impersonator::KEYCLOAK_CLIENT]['roles'] ?? [];
        if (in_array(Impersonator::KEYCLOAK_ROLE, $clientRoles, true)) {
            $roles[] = JwtUser::ROLE_IMPERSONATOR;
        }
        $canManageClients = in_array(self::KEYCLOAK_ROLE_MANAGE_CLIENTS, $clientRoles, true);
        if ($canManageClients || in_array(self::KEYCLOAK_ROLE_VIEW_CLIENTS, $clientRoles, true)) {
            $roles[] = JwtUser::ROLE_KEYCLOAK_VIEW_CLIENTS;
        }
        if ($canManageClients) {
            $roles[] = JwtUser::ROLE_KEYCLOAK_MANAGE_CLIENTS;
        }

        return new JwtUser(
            $token->toString(),
            $sub,
            $username,
            $roles,
            $claims->get('groups', []),
            $scopes,
        );
    }
}
