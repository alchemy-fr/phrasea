<?php

declare(strict_types=1);

namespace App\Controller;

use Alchemy\AuthBundle\Security\JwtOauthClient;
use Alchemy\AuthBundle\Security\JwtUser;
use App\Entity\Parade\Parade;
use App\Entity\Parade\ParadeTenant;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Symfony\Component\Routing\Attribute\Route;

class ParadeConfigAction extends AbstractController
{
    private const string GROUPS_SCOPE_ROLE = 'ROLE_GROUPS';

    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {
    }

    #[Route(path: '/parade-config', methods: ['GET'])]
    public function __invoke(): JsonResponse
    {
        $user = $this->getUser();
        if ($user instanceof JwtOauthClient) {
            $this->denyAccessUnlessGranted(self::GROUPS_SCOPE_ROLE);
        } elseif ($user instanceof JwtUser) {
            $this->denyAccessUnlessGranted(JwtUser::ROLE_ADMIN);
        } else {
            throw new UnauthorizedHttpException('Bearer', 'Authentication required');
        }

        $parade = $this->em->getRepository(Parade::class)->findOneBy([]);
        $tenants = $this->em->getRepository(ParadeTenant::class)->findBy([], ['slug' => 'ASC']);

        return new JsonResponse([
            'enabled' => null !== $parade && $parade->isEnabled(),
            'url' => $parade?->getUrl(),
            'groups' => $this->roleGroups($parade?->getGroups() ?? [], ['superAdmin', 'admin', 'technical', 'functional']),
            'tenants' => array_map(fn (ParadeTenant $tenant): array => [
                'slug' => $tenant->getSlug(),
                'name' => $tenant->getName(),
                'subRoles' => $tenant->hasSubRoles(),
                'groups' => $tenant->hasSubRoles() ? $this->roleGroups($tenant->getGroups(), ['admin', 'technical', 'functional']) : (object) [],
            ], $tenants),
        ]);
    }

    private function roleGroups(array $groups, array $roles): object
    {
        return (object) array_filter(
            array_intersect_key($groups, array_flip($roles)),
            fn (mixed $id): bool => is_string($id) && '' !== $id,
        );
    }
}
