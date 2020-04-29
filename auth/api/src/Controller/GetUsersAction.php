<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Security\Voter\UserVoter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class GetUsersAction extends AbstractController
{
    private EntityManagerInterface $em;

    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;
    }

    /**
     * @Route(path="/users")
     */
    public function __invoke(Request $request)
    {
        $limit = $request->query->get('limit', 200);
        $offset = $request->query->get('offset', 0);
        $limit = $limit > 200 ? 200 : $limit;

        $users = $this->em
            ->getRepository(User::class)
            ->findBy(
                [],
                ['username' => 'ASC'],
                $limit,
                $offset
            );

        $this->denyAccessUnlessGranted(UserVoter::LIST_USERS);

        return new JsonResponse(array_map(fn (User $user): array => [
            'id' => $user->getId(),
            'username' => $user->getUsername(),
            'email' => $user->getEmail(),
        ], $users));
    }
}
