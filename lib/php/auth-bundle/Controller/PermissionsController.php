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
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

#[Route(path: '/permissions', name: 'permissions_')]
class PermissionsController extends AbstractController
{
    public function __construct(
        private readonly UserRepositoryInterface $userRepository,
        private readonly GroupRepositoryInterface $groupRepository,
        private readonly TokenStorageInterface $tokenStorage,
    ) {
    }

    #[Route(path: '/users', name: 'users', methods: ['GET'])]
    public function getUsers(Request $request): Response
    {
        return new JsonResponse($this->userRepository->getUsers([
            'limit' => $request->query->get('limit', 30),
            'offset' => $request->query->get('offset'),
            'access_token' => $this->getAccessToken(),
            'query' => [
                'search' => $request->query->get('query'),
            ],
        ]));
    }

    #[Route(path: '/groups', name: 'groups', methods: ['GET'])]
    public function getGroups(Request $request): Response
    {
        return new JsonResponse($this->groupRepository->getGroups([
            'limit' => $request->query->get('limit', 30),
            'offset' => $request->query->get('offset'),
            'query' => [
                'search' => $request->query->get('query'),
            ],
            'access_token' => $this->getAccessToken(),
        ]));
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
