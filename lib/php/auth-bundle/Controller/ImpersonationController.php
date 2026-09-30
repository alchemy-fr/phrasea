<?php

declare(strict_types=1);

namespace Alchemy\AuthBundle\Controller;

use Alchemy\AuthBundle\Repository\UserRepositoryInterface;
use Alchemy\AuthBundle\Security\Impersonator;
use Alchemy\AuthBundle\Security\JwtUser;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Lets impersonators pick the user to act as. These routes must be called as
 * the real user (without the impersonation header).
 */
#[Route(path: '/impersonation', name: 'impersonation_')]
class ImpersonationController extends AbstractController
{
    public function __construct(
        private readonly UserRepositoryInterface $userRepository,
        private readonly Impersonator $impersonator,
    ) {
    }

    #[Route(path: '/users', name: 'users', methods: ['GET'])]
    public function getUsers(Request $request): JsonResponse
    {
        $this->denyUnlessAllowed();

        $query = array_filter([
            'search' => $request->query->get('query'),
            'first' => max(0, $request->query->getInt('offset')),
            'max' => min(100, max(1, $request->query->getInt('limit', 30))),
            'briefRepresentation' => 'true',
        ], fn ($v): bool => null !== $v && '' !== $v);

        $users = $this->userRepository->getUsers([
            'query' => $query,
        ]);

        return new JsonResponse(array_map(fn (array $user): array => [
            'id' => $user['id'],
            'username' => $user['username'] ?? $user['id'],
            'email' => $user['email'] ?? null,
            'firstName' => $user['firstName'] ?? null,
            'lastName' => $user['lastName'] ?? null,
            'enabled' => $user['enabled'] ?? true,
        ], $users));
    }

    #[Route(path: '/users/{id}', name: 'user', methods: ['GET'])]
    public function getIdentity(string $id): JsonResponse
    {
        $this->denyUnlessAllowed();

        try {
            return new JsonResponse($this->impersonator->getIdentity($id));
        } catch (AccessDeniedHttpException $e) {
            throw new NotFoundHttpException($e->getMessage(), $e);
        }
    }

    private function denyUnlessAllowed(): void
    {
        $user = $this->getUser();
        if (!$user instanceof JwtUser || !$this->impersonator->canImpersonate($user)) {
            throw new AccessDeniedHttpException('Missing impersonation permission');
        }
    }
}
