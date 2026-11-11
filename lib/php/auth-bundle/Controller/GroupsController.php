<?php

declare(strict_types=1);

namespace Alchemy\AuthBundle\Controller;

use Alchemy\AuthBundle\Client\KeycloakClient;
use Alchemy\AuthBundle\Exception\GroupAlreadyExistsException;
use Alchemy\AuthBundle\Repository\GroupRepositoryInterface;
use Alchemy\AuthBundle\Security\JwtUser;
use Alchemy\AuthBundle\Security\Traits\KeycloakTokenOwnerTrait;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Management of the Keycloak groups of the realm, sent to the Keycloak admin API with the token of
 * the user: Keycloak itself checks the "manage-users" role of "realm-management". Only top level
 * groups are created; subgroups are not listed apart.
 */
#[AsController]
#[Route(path: '/permissions/groups', name: 'groups_', requirements: ['id' => self::ID_PATTERN, 'userId' => self::ID_PATTERN])]
final class GroupsController
{
    use KeycloakTokenOwnerTrait;

    /**
     * The IDs become path segments of the Keycloak admin API.
     */
    private const string ID_PATTERN = '[A-Za-z0-9-]{1,64}';
    private const int MAX_NAME_LENGTH = 255;
    private const int DEFAULT_PAGE_SIZE = 30;
    private const int MAX_PAGE_SIZE = 100;

    public function __construct(
        private readonly KeycloakClient $keycloakClient,
        private readonly GroupRepositoryInterface $groupRepository,
    ) {
    }

    #[Route(path: '', name: 'create', methods: ['POST'])]
    public function create(Request $request): Response
    {
        $user = $this->getKeycloakTokenOwner(JwtUser::ROLE_KEYCLOAK_MANAGE_GROUPS);
        $name = $this->getName($request);
        if ($name instanceof Response) {
            return $name;
        }

        try {
            $group = $this->keycloakClient->createGroup(['name' => $name], $user->getJwt());
        } catch (GroupAlreadyExistsException) {
            return self::nameError(Response::HTTP_CONFLICT, 'A group with this name already exists.');
        } finally {
            $this->groupRepository->invalidateGroup();
        }

        return new JsonResponse(self::normalizeGroup($group), Response::HTTP_CREATED);
    }

    #[Route(path: '/{id}', name: 'update', methods: ['PUT'])]
    public function update(string $id, Request $request): Response
    {
        $user = $this->getKeycloakTokenOwner(JwtUser::ROLE_KEYCLOAK_MANAGE_GROUPS);
        $name = $this->getName($request);
        if ($name instanceof Response) {
            return $name;
        }

        try {
            $this->keycloakClient->updateGroup($id, ['name' => $name], $user->getJwt());
        } catch (GroupAlreadyExistsException) {
            return self::nameError(Response::HTTP_CONFLICT, 'A group with this name already exists.');
        } finally {
            $this->groupRepository->invalidateGroup($id);
        }

        $group = $this->keycloakClient->getGroup($user->getJwt(), $id) ?? throw new NotFoundHttpException('Group not found');

        return new JsonResponse(self::normalizeGroup($group));
    }

    #[Route(path: '/{id}', name: 'delete', methods: ['DELETE'])]
    public function delete(string $id): Response
    {
        $user = $this->getKeycloakTokenOwner(JwtUser::ROLE_KEYCLOAK_MANAGE_GROUPS);

        try {
            $this->keycloakClient->deleteGroup($id, $user->getJwt());
        } finally {
            $this->groupRepository->invalidateGroup($id);
        }

        return new Response(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * A page of the direct members of the group ("offset" and "limit").
     */
    #[Route(path: '/{id}/members', name: 'members', methods: ['GET'])]
    public function getMembers(string $id, Request $request): Response
    {
        $user = $this->getKeycloakTokenOwner(JwtUser::ROLE_KEYCLOAK_MANAGE_GROUPS);

        $members = $this->keycloakClient->getGroupMembers(
            $user->getJwt(),
            $id,
            max(0, $request->query->getInt('offset')),
            min(self::MAX_PAGE_SIZE, max(1, $request->query->getInt('limit', self::DEFAULT_PAGE_SIZE))),
        );

        return new JsonResponse(array_map(self::normalizeUser(...), $members));
    }

    #[Route(path: '/{id}/members/{userId}', name: 'add_member', methods: ['PUT'])]
    public function addMember(string $id, string $userId): Response
    {
        $user = $this->getKeycloakTokenOwner(JwtUser::ROLE_KEYCLOAK_MANAGE_GROUPS);
        $this->keycloakClient->addUserToGroup($userId, $id, $user->getJwt());

        return new Response(null, Response::HTTP_NO_CONTENT);
    }

    #[Route(path: '/{id}/members/{userId}', name: 'remove_member', methods: ['DELETE'])]
    public function removeMember(string $id, string $userId): Response
    {
        $user = $this->getKeycloakTokenOwner(JwtUser::ROLE_KEYCLOAK_MANAGE_GROUPS);
        $this->keycloakClient->removeUserFromGroup($userId, $id, $user->getJwt());

        return new Response(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * The trimmed "name" of the JSON body, or the error response refusing it.
     */
    private function getName(Request $request): string|Response
    {
        $name = $request->toArray()['name'] ?? null;
        $name = is_string($name) ? trim($name) : '';
        if ('' === $name || mb_strlen($name) > self::MAX_NAME_LENGTH) {
            return self::nameError(Response::HTTP_BAD_REQUEST, sprintf('The name must have between 1 and %d characters.', self::MAX_NAME_LENGTH));
        }

        return $name;
    }

    /**
     * An error on the name, as the clients read the constraint violations of API Platform.
     */
    private static function nameError(int $status, string $message): JsonResponse
    {
        return new JsonResponse([
            'title' => Response::$statusTexts[$status] ?? 'Error',
            'status' => $status,
            'detail' => $message,
            'violations' => [[
                'propertyPath' => 'name',
                'message' => $message,
            ]],
        ], $status);
    }

    private static function normalizeGroup(array $group): array
    {
        return [
            'id' => $group['id'],
            'name' => $group['name'] ?? '',
            'path' => $group['path'] ?? null,
        ];
    }

    private static function normalizeUser(array $user): array
    {
        return [
            'id' => $user['id'],
            'username' => $user['username'] ?? $user['id'],
            'firstName' => $user['firstName'] ?? null,
            'lastName' => $user['lastName'] ?? null,
            'email' => $user['email'] ?? null,
        ];
    }
}
