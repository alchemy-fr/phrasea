<?php

declare(strict_types=1);

namespace Alchemy\CoreBundle\Controller;

use Alchemy\CoreBundle\Pusher\ChannelAuthorizerInterface;
use Alchemy\CoreBundle\Pusher\PusherManager;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Pusher/Sockudo channel-authorization endpoint.
 *
 * The client (pusher-js) posts the connection socket id and the channel it
 * wants to subscribe to. The Sockudo key is public, so every channel carrying
 * data is private: only `private-` channels are signed here, and only when the
 * first tagged ChannelAuthorizerInterface supporting the channel grants the
 * current visitor, who may be anonymous (e.g. a public workspace).
 *
 * A refusal answers 401 to an anonymous visitor (logging in may grant the
 * channel) and 403 to an authenticated user.
 */
#[AsController]
final readonly class RealtimeAuthController
{
    /**
     * @param iterable<ChannelAuthorizerInterface> $authorizers
     */
    public function __construct(
        private Security $security,
        private PusherManager $pusherManager,
        #[AutowireIterator(ChannelAuthorizerInterface::TAG)]
        private iterable $authorizers,
    ) {
    }

    #[Route('/pusher/auth', name: 'alchemy_core_pusher_auth', methods: ['POST'])]
    public function auth(Request $request): Response
    {
        [$socketId, $channelName] = $this->readParams($request);

        if ('' === $socketId || '' === $channelName) {
            throw new BadRequestHttpException('Missing "socket_id" or "channel_name".');
        }

        if (!str_starts_with($channelName, PusherManager::PRIVATE_PREFIX)) {
            throw new AccessDeniedHttpException('Only private channels can be authorized.');
        }

        $authorizer = $this->getAuthorizer($channelName);
        if (null === $authorizer) {
            throw new AccessDeniedHttpException('Unknown channel.');
        }

        $user = $this->security->getUser();
        if (!$authorizer->isGranted($channelName, $user)) {
            if (null === $user) {
                throw new UnauthorizedHttpException('Bearer', 'Authentication required.');
            }

            throw new AccessDeniedHttpException('You are not allowed to subscribe to this channel.');
        }

        return new Response(
            $this->pusherManager->authorizeChannel($channelName, $socketId),
            Response::HTTP_OK,
            ['Content-Type' => 'application/json'],
        );
    }

    private function getAuthorizer(string $channelName): ?ChannelAuthorizerInterface
    {
        foreach ($this->authorizers as $authorizer) {
            if ($authorizer->supports($channelName)) {
                return $authorizer;
            }
        }

        return null;
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
