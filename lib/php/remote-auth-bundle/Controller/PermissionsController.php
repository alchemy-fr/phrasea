<?php

declare(strict_types=1);

namespace Alchemy\RemoteAuthBundle\Controller;

use Alchemy\RemoteAuthBundle\Repository\GroupRepositoryInterface;
use Alchemy\RemoteAuthBundle\Repository\UserRepositoryInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/permissions", name="permissions_")
 */
class PermissionsController extends AbstractController
{
    public function __construct(
        private readonly UserRepositoryInterface $userRepository,
        private readonly GroupRepositoryInterface $groupRepository,
    )
    {
    }

    /**
     * @Route("/users", methods={"GET"}, name="users")
     */
    public function getUsers(Request $request): Response
    {
        $this->validateAuthorization();
        $limit = $request->query->get('limit', 30);
        $offset = $request->query->get('offset');

        return new JsonResponse($this->userRepository->getUsers($limit, $offset));
    }

    /**
     * @Route("/groups", methods={"GET"}, name="groups")
     */
    public function getGroups(Request $request): Response
    {
        $this->validateAuthorization();
        $limit = $request->query->get('limit', 30);
        $offset = $request->query->get('offset');

        return new JsonResponse($this->groupRepository->getGroups($limit, $offset));
    }

    private function validateAuthorization(): void
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
    }
}