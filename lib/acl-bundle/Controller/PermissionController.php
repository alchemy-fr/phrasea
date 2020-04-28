<?php

declare(strict_types=1);

namespace Alchemy\AclBundle\Controller;

use Alchemy\AclBundle\Entity\AccessControlEntry;
use Alchemy\AclBundle\Mapping\ObjectMapping;
use Alchemy\AclBundle\Repository\GroupRepositoryInterface;
use Alchemy\AclBundle\Repository\PermissionRepositoryInterface;
use Alchemy\AclBundle\Repository\UserRepositoryInterface;
use Alchemy\AclBundle\Security\PermissionInterface;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class PermissionController extends AbstractController
{
    private ObjectMapping $objectMapping;
    private PermissionRepositoryInterface $repository;
    private UserRepositoryInterface $userRepository;
    private GroupRepositoryInterface $groupRepository;

    public function __construct(
        ObjectMapping $objectMapping,
        PermissionRepositoryInterface $repository,
        UserRepositoryInterface $userRepository,
        GroupRepositoryInterface $groupRepository
    ) {
        $this->objectMapping = $objectMapping;
        $this->repository = $repository;
        $this->userRepository = $userRepository;
        $this->groupRepository = $groupRepository;
    }

    private function validateAuthorization(): void
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
    }

    public function acl(string $entityClass, string $id): Response
    {
        $this->validateAuthorization();
        $objectKey = $this->objectMapping->getObjectKey($entityClass);
        $permissions = PermissionInterface::PERMISSIONS;
        $aces = $this->repository->getObjectAces($objectKey, $id);

        $users = [];
        foreach ($this->userRepository->getUsers() as $user) {
            $users[$user['id']] = $user['username'];
        }
        $groups = [];
        foreach ($this->groupRepository->getGroups() as $group) {
            $groups[$group['id']] = $group['name'];
        }

        $aces = array_map(function (AccessControlEntry $ace) use ($users, $groups, $permissions): array {
            $name = $ace->getEntityId();
            switch ($ace->getEntityType()) {
                case AccessControlEntry::ENTITY_USER:
                    $name = $users[$ace->getEntityId()] ?? $name;
                    break;
                case AccessControlEntry::ENTITY_GROUP:
                    $name = $groups[$ace->getEntityId()] ?? $name;
                    break;
            }

            return [
                'entityType' => $ace->getEntityTypeString(),
                'entityId' => $ace->getEntityId(),
                'name' => $name,
                'permissions' => array_map(fn (int $p): bool => $ace->hasPermission($p), $permissions),
            ];
        }, $aces);

        return $this->render('@AlchemyAcl/permissions/acl.html.twig', [
            'object_type' => $objectKey,
            'object_id' => $id,
            'permissions' => $permissions,
            'aces' => $aces,
            'users' => $users,
            'groups' => $groups,
        ]);
    }

    /**
     * @Route("/ace", methods={"PUT"}, name="ace")
     */
    public function setAce(Request $request, PermissionRepositoryInterface $repository): Response
    {
        $this->validateAuthorization();
        $objectType = $request->request->get('objectType');
        $objectId = $request->request->get('objectId');
        $entityType = $request->request->get('entityType');
        $entityId = $request->request->get('entityId');
        $mask = (int) $request->request->get('mask', 0);

        $repository->updateOrCreateAce($entityType, $entityId, $objectType, $objectId, $mask);

        return new JsonResponse(true);
    }

    /**
     * @Route("/ace", methods={"DELETE"}, name="ace_delete")
     */
    public function deleteAce(Request $request, PermissionRepositoryInterface $repository): Response
    {
        $this->validateAuthorization();
        $objectType = $request->request->get('objectType');
        $objectId = $request->request->get('objectId');
        $entityType = $request->request->get('entityType');
        $entityId = $request->request->get('entityId');

        $repository->deleteAce($entityType, $entityId, $objectType, $objectId);

        return new JsonResponse(true);
    }

    /**
     * @Route("/users", methods={"GET"}, name="users")
     */
    public function getUsers(Request $request, UserRepositoryInterface $repository): Response
    {
        $this->validateAuthorization();
        $limit = $request->query->get('limit');
        $offset = $request->query->get('offset');

        return new JsonResponse($repository->getUsers($limit, $offset));
    }

    /**
     * @Route("/groups", methods={"GET"}, name="groups")
     */
    public function getGroups(Request $request, GroupRepositoryInterface $repository): Response
    {
        $this->validateAuthorization();
        $limit = $request->query->get('limit');
        $offset = $request->query->get('offset');

        return new JsonResponse($repository->getGroups($limit, $offset));
    }
}
