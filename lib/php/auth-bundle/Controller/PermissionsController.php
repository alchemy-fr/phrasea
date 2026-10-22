<?php

declare(strict_types=1);

namespace Alchemy\AuthBundle\Controller;

use Alchemy\AuthBundle\Repository\GroupRepositoryInterface;
use Alchemy\AuthBundle\Repository\UserRepositoryInterface;
use Alchemy\AuthBundle\Security\Token\JwtToken;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

#[Route(path: '/permissions', name: 'permissions_')]
class PermissionsController extends AbstractController
{
    private const int MAX_IDS = 50;

    public function __construct(
        private readonly UserRepositoryInterface $userRepository,
        private readonly GroupRepositoryInterface $groupRepository,
        private readonly TokenStorageInterface $tokenStorage,
    ) {
    }

    /**
     * A page of the users matching "query" ("limit" and "offset"), or the users of "ids[]" (unknown ones are left out).
     */
    #[Route(path: '/users', name: 'users', methods: ['GET'])]
    public function getUsers(Request $request): Response
    {
        $accessToken = $this->getAccessToken();

        if (null !== $ids = $this->getIds($request)) {
            return new JsonResponse(array_values(array_filter(array_map(
                fn (string $id): ?array => $this->userRepository->getUser($id),
                $ids,
            ))));
        }

        return new JsonResponse($this->userRepository->getUsers([
            'access_token' => $accessToken,
            'query' => $this->getPageQuery($request),
        ]));
    }

    /**
     * A page of the groups matching "query" ("limit" and "offset"), or the groups of "ids[]" (unknown ones are left out).
     */
    #[Route(path: '/groups', name: 'groups', methods: ['GET'])]
    public function getGroups(Request $request): Response
    {
        $accessToken = $this->getAccessToken();

        if (null !== $ids = $this->getIds($request)) {
            return new JsonResponse(array_values(array_filter(array_map(
                fn (string $id): ?array => $this->groupRepository->getGroup($id),
                $ids,
            ))));
        }

        return new JsonResponse($this->groupRepository->getGroups([
            'access_token' => $accessToken,
            'query' => $this->getPageQuery($request),
        ]));
    }

    /**
     * Keycloak pages with "first" and "max".
     */
    private function getPageQuery(Request $request): array
    {
        return array_filter([
            'search' => $request->query->get('query'),
            'first' => max(0, $request->query->getInt('offset')),
            'max' => min(100, max(1, $request->query->getInt('limit', 30))),
            'briefRepresentation' => 'true',
        ], fn ($v): bool => null !== $v && '' !== $v);
    }

    /**
     * @return list<string>|null
     */
    private function getIds(Request $request): ?array
    {
        if (!$request->query->has('ids')) {
            return null;
        }

        $ids = array_values(array_unique($request->query->all('ids')));
        if (count($ids) > self::MAX_IDS) {
            throw new BadRequestHttpException(sprintf('At most %d "ids" are allowed', self::MAX_IDS));
        }

        foreach ($ids as $id) {
            // The ID becomes a path segment of the Keycloak admin API
            if (!is_string($id) || 1 !== preg_match('/^[A-Za-z0-9-]{1,64}$/', $id)) {
                throw new BadRequestHttpException('Invalid "ids"');
            }
        }

        return $ids;
    }

    private function getAccessToken(): string
    {
        $accessToken = $this->tokenStorage->getToken();

        if ($accessToken instanceof JwtToken) {
            return $accessToken->getAccessToken();
        }

        throw new UnauthorizedHttpException('Missing access token');
    }
}
