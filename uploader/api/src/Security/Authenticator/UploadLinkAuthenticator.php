<?php

declare(strict_types=1);

namespace App\Security\Authenticator;

use Alchemy\AuthBundle\Security\RequestHelper;
use App\Entity\UploadLink;
use App\Repository\UploadLinkRepository;
use App\Security\UploadLinkUser;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Component\Uid\Uuid;

/**
 * `Authorization: UploadLink <token>`: the token of an upload link, which grants uploading to its target.
 *
 * The browser adds its own session id (`X-Upload-Session`, a UUID) to tell apart the people sharing the link.
 */
final class UploadLinkAuthenticator extends AbstractAuthenticator
{
    final public const string SCHEME = 'UploadLink';

    final public const string ERROR_INVALID = 'upload_link_invalid';
    final public const string ERROR_DISABLED = 'upload_link_disabled';
    final public const string ERROR_EXPIRED = 'upload_link_expired';

    public function __construct(
        private readonly UploadLinkRepository $repository,
    ) {
    }

    public function supports(Request $request): bool
    {
        return null !== RequestHelper::getAuthorizationFromRequest($request, self::SCHEME, false);
    }

    public function authenticate(Request $request): Passport
    {
        $token = (string) RequestHelper::getAuthorizationFromRequest($request, self::SCHEME, false);
        $link = '' !== $token ? $this->repository->findOneByToken($token) : null;

        if (!$link instanceof UploadLink) {
            throw new CustomUserMessageAuthenticationException('This upload link is invalid', ['code' => self::ERROR_INVALID]);
        }
        if (!$link->isEnabled()) {
            throw new CustomUserMessageAuthenticationException('This upload link has been disabled', ['code' => self::ERROR_DISABLED]);
        }
        if ($link->isExpired(new \DateTimeImmutable())) {
            throw new CustomUserMessageAuthenticationException('This upload link has expired', ['code' => self::ERROR_EXPIRED]);
        }

        $user = new UploadLinkUser($link->getId(), $link->getTarget()->getId(), (string) $link->getOwnerId(), $this->getSessionId($request));

        return new SelfValidatingPassport(new UserBadge($user->getUserIdentifier(), static fn (): UploadLinkUser => $user));
    }

    private function getSessionId(Request $request): ?string
    {
        $sessionId = $request->headers->get(UploadLinkUser::SESSION_HEADER);
        if (null === $sessionId || '' === $sessionId) {
            return null;
        }
        if (!Uuid::isValid($sessionId)) {
            throw new BadRequestHttpException(sprintf('Invalid %s header: a UUID is expected', UploadLinkUser::SESSION_HEADER));
        }

        return strtolower($sessionId);
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        return null;
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        $data = $exception->getMessageData();

        return new JsonResponse([
            'code' => $data['code'] ?? self::ERROR_INVALID,
            'message' => $exception->getMessageKey(),
        ], Response::HTTP_UNAUTHORIZED);
    }
}
