<?php

declare(strict_types=1);

namespace Alchemy\CoreBundle\Controller;

use Alchemy\CoreBundle\Pusher\PusherManager;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Pusher/Soketi channel-authorization endpoint for private per-user channels.
 *
 * The client (pusher-js) posts the connection socket id and the channel it
 * wants to subscribe to; we authenticate the user, ensure the requested channel
 * is their OWN private channel (`{prefix}{userIdentifier}`), and return the
 * signed auth token so Soketi lets the subscription through. This is what makes
 * per-user channels (e.g. in-app notifications) actually private.
 */
#[AsController]
final readonly class RealtimeAuthController
{
    public function __construct(
        private Security $security,
        private PusherManager $pusherManager,
        private string $channelPrefix = 'private-user-',
    ) {
    }

    #[Route('/pusher/auth', name: 'alchemy_core_pusher_auth', methods: ['POST'])]
    public function auth(Request $request): Response
    {
        $user = $this->security->getUser();
        if (null === $user) {
            throw new UnauthorizedHttpException('Bearer', 'Authentication required.');
        }

        [$socketId, $channelName] = $this->readParams($request);

        if ('' === $socketId || '' === $channelName) {
            throw new BadRequestHttpException('Missing "socket_id" or "channel_name".');
        }

        // The only channel a user may ever authorize is their own private
        // channel. Without this check any authenticated user could subscribe to
        // someone else's channel and read their real-time events.
        $expected = $this->pusherManager->normalizeChannel($this->channelPrefix.$user->getUserIdentifier());
        if (!hash_equals($expected, $channelName)) {
            throw new AccessDeniedHttpException('You are not allowed to subscribe to this channel.');
        }

        return new Response(
            $this->pusherManager->authorizeChannel($channelName, $socketId),
            Response::HTTP_OK,
            ['Content-Type' => 'application/json'],
        );
    }

    /**
     * Reads socket_id / channel_name from either a JSON body (our pusher-js
     * customHandler) or a classic form-encoded body (default Pusher clients).
     *
     * @return array{0: string, 1: string}
     */
    private function readParams(Request $request): array
    {
        $socketId = $request->request->get('socket_id');
        $channelName = $request->request->get('channel_name');

        if (null === $socketId && null === $channelName
            && str_contains((string) $request->headers->get('Content-Type'), 'json')
        ) {
            $data = json_decode($request->getContent(), true);
            if (is_array($data)) {
                $socketId = $data['socket_id'] ?? null;
                $channelName = $data['channel_name'] ?? null;
            }
        }

        return [(string) $socketId, (string) $channelName];
    }
}
