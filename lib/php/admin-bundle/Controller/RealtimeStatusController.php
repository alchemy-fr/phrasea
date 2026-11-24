<?php

declare(strict_types=1);

namespace Alchemy\AdminBundle\Controller;

use Alchemy\AdminBundle\Utils\UserDisplayNameResolver;
use Alchemy\AuthBundle\Security\JwtUser;
use Alchemy\CoreBundle\Pusher\RealtimeStatusProvider;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminRoute;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * State of the Sockudo server and the users connected to it.
 */
#[AdminRoute(path: '/realtime', name: 'realtime_status')]
#[IsGranted(JwtUser::ROLE_ADMIN)]
final class RealtimeStatusController extends AbstractController
{
    public const string ROUTE_NAME = 'easyadmin_realtime_status';

    public function __construct(
        private readonly UserDisplayNameResolver $userDisplayNameResolver,
        // Null when the application does not enable the Pusher integration of the core bundle.
        private readonly ?RealtimeStatusProvider $statusProvider = null,
    ) {
    }

    public function __invoke(): Response
    {
        $status = $this->statusProvider?->getStatus();
        [$users, $usersError] = null !== $status ? $this->resolveUsers($status->users) : [[], null];

        return $this->render('@AlchemyAdmin/realtime_status.html.twig', [
            'status' => $status,
            'users' => $users,
            'usersError' => $usersError,
        ]);
    }

    /**
     * @param array<string|int, ?int> $connections user ID => connections
     *
     * @return array{0: list<array{id: string, name: ?string, known: ?bool, connections: ?int}>, 1: ?string}
     */
    private function resolveUsers(array $connections): array
    {
        if ([] === $connections) {
            return [[], null];
        }

        ['names' => $names, 'error' => $error] = $this->userDisplayNameResolver->resolve(array_map(strval(...), array_keys($connections)));

        $users = [];
        foreach ($connections as $id => $count) {
            $id = (string) $id;
            $users[] = [
                'id' => $id,
                'name' => $names[$id] ?? null,
                'known' => null === $error ? array_key_exists($id, $names) : null,
                'connections' => $count,
            ];
        }

        usort($users, static fn (array $a, array $b): int => [null === $a['name'], strtolower($a['name'] ?? $a['id'])]
            <=> [null === $b['name'], strtolower($b['name'] ?? $b['id'])]);

        return [$users, $error];
    }
}
