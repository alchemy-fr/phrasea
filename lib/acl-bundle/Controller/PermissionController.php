<?php

declare(strict_types=1);

namespace Alchemy\AclBundle\Controller;

use Alchemy\AclBundle\Repository\GroupRepositoryInterface;
use Alchemy\AclBundle\Repository\PermissionRepositoryInterface;
use Alchemy\AclBundle\Repository\UserRepositoryInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class PermissionController extends AbstractController
{
    private PermissionRepositoryInterface $repository;

    public function __construct(PermissionRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    private function validateAuthorization(): void
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
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
