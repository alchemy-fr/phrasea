<?php

declare(strict_types=1);

namespace App\Controller;

use Alchemy\OAuthServerBundle\Entity\AccessToken;
use App\Entity\User;
use FOS\OAuthServerBundle\Model\TokenManagerInterface;
use FOS\OAuthServerBundle\Security\Authentication\Token\OAuthToken;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Security;

class TokenInfoAction extends AbstractController
{
    public function __construct(private readonly Security $security, private readonly TokenManagerInterface $tokenManager)
    {
    }

    #[Route(path: '/token-info')]
    public function __invoke()
    {
        $token = $this->security->getToken();
        if (!$token instanceof OAuthToken) {
            throw new UnauthorizedHttpException(sprintf('Unsupported authentication token %s', get_debug_type($token)));
        }

        /** @var AccessToken $accessToken */
        $accessToken = $this->tokenManager->findTokenByToken($token->getToken());
        /** @var User|null $user */
        $user = $accessToken->getUser();

        $data = [
            'scopes' => $accessToken->getScope() ? array_filter(explode(' ', trim($accessToken->getScope()))) : [],
        ];

        if (null !== $user) {
            $data['user'] = [
                'id' => $user->getId(),
                'username' => $user->getUsername(),
                'email' => $user->getEmail(),
                'groups' => $user->getIndexedGroups(),
                'roles' => $user->getRoles(),
            ];
        }

        return new JsonResponse($data);
    }
}
