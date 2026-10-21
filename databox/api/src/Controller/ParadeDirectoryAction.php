<?php

declare(strict_types=1);

namespace App\Controller;

use Alchemy\AuthBundle\Client\KeycloakClient;
use Alchemy\AuthBundle\Client\ServiceAccountClient;
use Alchemy\AuthBundle\Security\JwtOauthClient;
use Alchemy\AuthBundle\Security\JwtUser;
use App\Entity\Parade\Parade;
use App\Entity\Parade\ParadeTenant;
use App\Parade\ParadeProvisioner;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Symfony\Component\Routing\Attribute\Route;

class ParadeDirectoryAction extends AbstractController
{
    private const string GROUPS_SCOPE_ROLE = 'ROLE_GROUPS';
    private const string ID_PATTERN = '[0-9a-fA-F-]{36}';
    private const int MAX_RESULTS = 20;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ParadeProvisioner $provisioner,
        private readonly KeycloakClient $keycloakClient,
        private readonly ServiceAccountClient $serviceAccountClient,
        private readonly LoggerInterface $logger,
    ) {
    }

    #[Route(path: '/parade-users', methods: ['GET'])]
    public function users(Request $request): JsonResponse
    {
        $this->authorize();
        $query = trim((string) $request->query->get('query', ''));
        if (mb_strlen($query) < 2 || mb_strlen($query) > 100) {
            return new JsonResponse([]);
        }

        $users = $this->serviceAccountClient->executeWithAccessToken(
            fn (string $accessToken): array => $this->keycloakClient->getUsers($accessToken, [
                'query' => ['search' => $query, 'max' => self::MAX_RESULTS, 'briefRepresentation' => 'true'],
            ])
        );

        return new JsonResponse(array_map($this->user(...), $users));
    }

    #[Route(path: '/parade-groups/{groupId}/members', requirements: ['groupId' => self::ID_PATTERN], methods: ['GET'])]
    public function members(string $groupId): JsonResponse
    {
        $this->authorize();
        $this->assertManaged($groupId);

        return new JsonResponse(array_map($this->user(...), $this->provisioner->listMembers($groupId)));
    }

    #[Route(path: '/parade-groups/{groupId}/members/{userId}', requirements: ['groupId' => self::ID_PATTERN, 'userId' => self::ID_PATTERN], methods: ['PUT', 'DELETE'])]
    public function member(string $groupId, string $userId, Request $request): JsonResponse
    {
        $this->authorize();
        $this->assertManaged($groupId);
        $actor = (string) $request->headers->get('X-Parade-Actor', '');

        if ($request->isMethod('DELETE')) {
            $this->provisioner->removeMember($groupId, $userId);
        } else {
            $this->provisioner->addMember($groupId, $userId);
        }
        $this->logger->info('Parade group membership changed', [
            'operation' => $request->isMethod('DELETE') ? 'remove' : 'add',
            'groupId' => $groupId,
            'userId' => $userId,
            'actor' => mb_substr($actor, 0, 200),
        ]);

        return new JsonResponse(['groupId' => $groupId, 'userId' => $userId]);
    }

    private function authorize(): void
    {
        $user = $this->getUser();
        if ($user instanceof JwtOauthClient) {
            $this->denyAccessUnlessGranted(self::GROUPS_SCOPE_ROLE);
        } elseif ($user instanceof JwtUser) {
            $this->denyAccessUnlessGranted(JwtUser::ROLE_ADMIN);
        } else {
            throw new UnauthorizedHttpException('Bearer', 'Authentication required');
        }
    }

    private function assertManaged(string $groupId): void
    {
        $parade = $this->em->getRepository(Parade::class)->findOneBy([]);
        $managed = array_values(array_intersect_key($parade?->getGroups() ?? [], array_flip(['superAdmin', 'admin', 'technical', 'functional'])));
        foreach ($this->em->getRepository(ParadeTenant::class)->findBy(['removedAt' => null, 'subRoles' => true]) as $tenant) {
            $managed = [...$managed, ...array_values(array_intersect_key($tenant->getGroups(), array_flip(['admin', 'technical', 'functional'])))];
        }
        if (!in_array($groupId, $managed, true)) {
            throw new NotFoundHttpException('This group is not managed for Parade.');
        }
    }

    private function user(array $user): array
    {
        return [
            'id' => $user['id'] ?? null,
            'username' => $user['username'] ?? null,
            'email' => $user['email'] ?? null,
            'firstName' => $user['firstName'] ?? null,
            'lastName' => $user['lastName'] ?? null,
        ];
    }
}
