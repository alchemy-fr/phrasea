<?php

declare(strict_types=1);

namespace App\Controller;

use Alchemy\AuthBundle\Security\JwtUser;
use App\Entity\Parade\Parade;
use App\Entity\Parade\ParadeTenant;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

class ParadeStatusAction extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {
    }

    #[Route(path: '/parade-status', methods: ['GET'])]
    public function __invoke(): JsonResponse
    {
        $parade = $this->em->getRepository(Parade::class)->findOneBy([]);
        $enabled = null !== $parade && $parade->isEnabled();
        $user = $this->getUser();

        return new JsonResponse([
            'enabled' => $enabled,
            'url' => $parade?->getUrl(),
            'canAdminister' => $enabled && $user instanceof JwtUser && [] !== array_intersect($user->getGroups(), $this->adminGroupIds($parade)),
        ]);
    }

    private function adminGroupIds(Parade $parade): array
    {
        $groups = array_intersect_key($parade->getGroups(), array_flip(['superAdmin', 'admin', 'technical', 'functional']));
        foreach ($this->em->getRepository(ParadeTenant::class)->findBy(['subRoles' => true]) as $tenant) {
            $groups = [...$groups, ...array_values(array_intersect_key($tenant->getGroups(), array_flip(['admin', 'technical', 'functional'])))];
        }

        return array_values(array_filter($groups, fn (mixed $id): bool => is_string($id) && '' !== $id));
    }
}
