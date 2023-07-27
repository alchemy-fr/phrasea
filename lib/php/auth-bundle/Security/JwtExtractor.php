<?php

declare(strict_types=1);

namespace Alchemy\AuthBundle\Security;

use Lcobucci\JWT\Encoding\JoseEncoder;
use Lcobucci\JWT\Token\Parser;
use Lcobucci\JWT\UnencryptedToken;

final readonly class JwtExtractor
{
    private Parser $parser;

    public function __construct(
        private RoleMapper $roleMapper,
    )
    {
        $this->parser = new Parser(new JoseEncoder());
    }

    public function parseJwt(string $jwt): UnencryptedToken
    {
        $token = $this->parser->parse($jwt);

        if (!$token instanceof UnencryptedToken) {
            throw new \InvalidArgumentException('Token is not unencrypted');
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

        if (empty($claims->get('preferred_username'))) {
            throw new \InvalidArgumentException('Missing "preferred_username" from Keycloak');
        }

        return new JwtUser(
            $token->toString(),
            $claims->get('sub'),
            $claims->get('preferred_username'),
            $this->roleMapper->getRoles($claims->get('roles', [])),
            $claims->get('groups', []),
            $scopes,
        );
    }
}
