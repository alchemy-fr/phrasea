<?php

declare(strict_types=1);

namespace App\Controller;

use App\Model\User;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class MeAction extends AbstractController
{
    /**
     * @Route(path="/me", methods={"GET"})
     */
    public function __invoke(Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        return new JsonResponse([
            'user_id' => $user->getId(),
            'email' => $user->getUsername(),
            'is_admin' => $this->isGranted('ROLE_ADMIN'),
        ]);
    }
}
