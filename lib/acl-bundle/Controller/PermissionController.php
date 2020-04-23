<?php

declare(strict_types=1);

namespace Alchemy\AclBundle\Controller;

use Alchemy\AclBundle\Entity\AccessControlEntry;
use Alchemy\AclBundle\Mapping\ObjectMapping;
use Alchemy\AclBundle\Repository\PermissionRepositoryInterface;
use Alchemy\AclBundle\Repository\UserRepositoryInterface;
use Alchemy\AclBundle\Security\PermissionInterface;
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

    public function __construct(
        ObjectMapping $objectMapping,
        PermissionRepositoryInterface $repository,
        UserRepositoryInterface $userRepository
    )
    {
        $this->objectMapping = $objectMapping;
        $this->repository = $repository;
        $this->userRepository = $userRepository;
    }

    public function acl(string $entityClass, string $id): Response
    {
        $objectKey = $this->objectMapping->getObjectKey($entityClass);

        $permissions = PermissionInterface::PERMISSIONS;

        $aces = $this->repository->getObjectAces($objectKey, $id);

        $users = [];
        foreach ($this->userRepository->getUsers() as $user) {
            $users[$user['id']] = $user['username'];
        }

        $aces = array_map(function (AccessControlEntry $ace) use ($users, $permissions): array {
            return [
                'userId' => $ace->getUserId(),
                'username' => $users[$ace->getUserId()] ?? $ace->getUserId(),
                'permissions' => array_map(fn (int $p): bool => $ace->hasPermission($p), $permissions),
            ];
        }, $aces);

        return $this->render('@AlchemyAcl/permissions/acl.html.twig', [
            'object_type' => $objectKey,
            'object_id' => $id,
            'permissions' => $permissions,
            'aces' => $aces,
            'users' => $users,
        ]);
    }

    /**
     * @Route("/ace", methods={"PUT"}, name="ace")
     */
    public function setAce(Request $request, PermissionRepositoryInterface $repository): Response
    {
        $objectType = $request->request->get('objectType');
        $objectId = $request->request->get('objectId');
        $userId = $request->request->get('userId');
        $mask = (int) $request->request->get('mask', 0);

        $repository->updateOrCreateAce($userId, $objectType, $objectId, $mask);

        return new JsonResponse(true);
    }

    /**
     * @Route("/ace", methods={"DELETE"}, name="ace_delete")
     */
    public function deleteAce(Request $request, PermissionRepositoryInterface $repository): Response
    {
        $objectType = $request->request->get('objectType');
        $objectId = $request->request->get('objectId');
        $userId = $request->request->get('userId');

        $repository->deleteAce($userId, $objectType, $objectId);

        return new JsonResponse(true);
    }
}
