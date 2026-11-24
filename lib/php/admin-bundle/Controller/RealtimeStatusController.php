<?php

declare(strict_types=1);

namespace Alchemy\AdminBundle\Controller;

use Alchemy\AuthBundle\Repository\UserRepositoryInterface;
use Alchemy\AuthBundle\Security\JwtUser;
use Alchemy\CoreBundle\Pusher\RealtimeStatusProvider;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminRoute;
use Psr\Log\LoggerInterface;
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
        private readonly UserRepositoryInterface $userRepository,
        private readonly LoggerInterface $logger,
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

        $ids = array_map(strval(...), array_keys($connections));
        $error = null;
        $keycloakUsers = null;
        try {
            $keycloakUsers = $this->userRepository->getUsersByIds($ids);
        } catch (\Throwable $e) {
            $this->logger->warning('Cannot resolve the connected users: {message}', [
                'message' => $e->getMessage(),
                'exception' => $e,
            ]);
            $error = $e->getMessage();
        }

        $users = [];
        foreach ($connections as $id => $count) {
            $id = (string) $id;
            $user = $keycloakUsers[$id] ?? null;
            $users[] = [
                'id' => $id,
                'name' => null !== $user ? self::getDisplayName($user) : null,
                'known' => null !== $keycloakUsers ? null !== $user : null,
                'connections' => $count,
            ];
        }

        usort($users, static fn (array $a, array $b): int => [null === $a['name'], strtolower($a['name'] ?? $a['id'])]
            <=> [null === $b['name'], strtolower($b['name'] ?? $b['id'])]);

        return [$users, $error];
    }

    private static function getDisplayName(array $user): ?string
    {
        $fullName = trim(($user['firstName'] ?? '').' '.($user['lastName'] ?? ''));
        $username = $user['username'] ?? null;

        if (null === $username || '' === $username) {
            return '' !== $fullName ? $fullName : null;
        }

        return '' !== $fullName ? sprintf('%s (%s)', $username, $fullName) : $username;
    }
}
