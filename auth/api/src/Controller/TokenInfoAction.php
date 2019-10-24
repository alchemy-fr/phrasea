<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\AccessToken;
use App\Entity\User;
use FOS\OAuthServerBundle\Model\TokenManagerInterface;
use FOS\OAuthServerBundle\Security\Authentication\Token\OAuthToken;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Security;

class TokenInfoAction extends AbstractController
{
    /**
     * @var TokenManagerInterface
     */
    private $tokenManager;
    /**
     * @var Security
     */
    private $security;

    public function __construct(Security $security, TokenManagerInterface $tokenManager)
    {
        $this->tokenManager = $tokenManager;
        $this->security = $security;
    }

    /**
     * @Route(path="/token-info")
     */
    public function __invoke()
    {
        $token = $this->security->getToken();
        if (!$token instanceof OAuthToken) {
            throw new AccessDeniedHttpException('Unsupported authentication token %s', get_class($token));
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
                'email' => $user->getEmail(),
                'roles' => $user->getRoles(),
            ];
        }

        return new JsonResponse($data);
    }
}
