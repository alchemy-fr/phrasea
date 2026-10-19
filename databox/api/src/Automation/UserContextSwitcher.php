<?php

declare(strict_types=1);

namespace App\Automation;

use Alchemy\AuthBundle\Security\Impersonator;
use Alchemy\AuthBundle\Security\JwtUser;
use Alchemy\AuthBundle\Security\RoleMapper;
use Alchemy\AuthBundle\Security\Token\JwtToken;
use App\Automation\Exception\AutomationOwnerUnavailableException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

/**
 * Runs code as a given user, outside of any HTTP request: the user's current
 * roles and groups are read from the IdP at each call (never a snapshot).
 */
final class UserContextSwitcher
{
    // Not a real JWT: the token is never sent anywhere
    private const string FAKE_JWT = 'automation';

    /**
     * Voters cache their decisions by spl_object_id() of the token: the tokens are kept alive
     * so that the ID of a released token is never reused by the token of another user.
     *
     * @var TokenInterface[]
     */
    private array $tokens = [];

    public function __construct(
        private readonly TokenStorageInterface $tokenStorage,
        private readonly Impersonator $impersonator,
        private readonly RoleMapper $roleMapper,
    ) {
    }

    /**
     * @throws AutomationOwnerUnavailableException
     */
    public function createUser(string $userId): JwtUser
    {
        try {
            $identity = $this->impersonator->getIdentity($userId);
        } catch (HttpExceptionInterface $e) {
            throw new AutomationOwnerUnavailableException(sprintf('User "%s" is not available: %s', $userId, $e->getMessage()), 0, $e);
        }

        return new JwtUser(
            self::FAKE_JWT,
            $userId,
            $identity['username'],
            $this->roleMapper->getRoles($identity['roles']),
            $identity['groups'],
        );
    }

    /**
     * @template T
     *
     * @param callable(JwtUser): T $fn
     *
     * @return T
     *
     * @throws AutomationOwnerUnavailableException
     */
    public function runAs(string $userId, callable $fn): mixed
    {
        $user = $this->createUser($userId);

        $previousToken = $this->tokenStorage->getToken();
        $token = new JwtToken(self::FAKE_JWT, $user->getRoles());
        $token->setUser($user);
        $this->tokens[] = $token;
        $this->tokenStorage->setToken($token);

        try {
            return $fn($user);
        } finally {
            $this->tokenStorage->setToken($previousToken);
        }
    }
}
