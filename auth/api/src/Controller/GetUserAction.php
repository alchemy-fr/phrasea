<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

class GetUserAction extends AbstractController
{
    /**
     * @var EntityManagerInterface
     */
    private $em;

    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;
    }

    /**
     * @Route(path="/users/{id}")
     */
    public function __invoke(string $id)
    {
        $user = $this->em->find(User::class, $id);

        $this->denyAccessUnlessGranted('READ', $user);

        return new JsonResponse([
            'id' => $user->getId(),
            'email' => $user->getEmail(),
            'locale' => $user->getLocale(),
            'createdAt' => $user->getCreatedAt()->format(DateTime::ISO8601),
        ]);
    }
}
