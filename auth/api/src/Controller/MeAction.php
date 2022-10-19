<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Security;

class MeAction extends AbstractController
{
    /**
     * @var Security
     */
    private $security;

    public function __construct(Security $security)
    {
        $this->security = $security;
    }

    /**
     * @Route(path="/me")
     * @Route(path="/userinfo")
     */
    public function __invoke()
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

        $token = $this->security->getToken();
        $user = $token->getUser();

        if ($user instanceof User) {
            $data = [
                'user_id' => $user->getId(),
                'username' => $user->getUsername(),
                'email' => $user->getEmail(),
                'roles' => $user->getRoles(),
                'groups' => $user->getIndexedGroups(),
            ];
        } else {
            $data = [
                'roles' => $token->getRoleNames(),
            ];
        }

        return new JsonResponse($data);
    }
}
