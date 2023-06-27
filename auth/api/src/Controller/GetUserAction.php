<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

class GetUserAction extends AbstractController
{
    private EntityManagerInterface $em;

    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;
    }

    /**
     * @Route(path="/users/{id}")
     */
    public function __invoke(string $id)
    {
        /** @var User $user */
        $user = $this->em->find(User::class, $id);

        $this->denyAccessUnlessGranted('READ', $user);

        return new JsonResponse([
            'id' => $user->getId(),
            'username' => $user->getUsername(),
            'email' => $user->getEmail(),
            'groups' => $user->getIndexedGroups(),
            'locale' => $user->getLocale(),
            'createdAt' => $user->getCreatedAt()->format(\DateTime::ISO8601),
        ]);
    }
}
