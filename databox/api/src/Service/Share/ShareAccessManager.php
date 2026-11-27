<?php

declare(strict_types=1);

namespace App\Service\Share;

use App\Entity\Core\Share;
use App\Model\ShareTokenAccessEnum;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Anonymous access to a share link: its token, then, for a share protected by a password,
 * the access token handed out once the password is given (POST /s/{id}/unlock).
 *
 * The access token is "<expiresAt>.<signature>", a signature of the share, the expiration
 * and the current password hash: changing or removing the password revokes the access
 * tokens already handed out.
 */
final readonly class ShareAccessManager
{
    public const int ACCESS_TTL = 86400;
    public const string TOKEN_PARAM = 'token';
    public const string QUERY_PARAM = 'access';

    public function __construct(
        private RequestStack $requestStack,
        #[Autowire('%kernel.secret%')]
        private string $secret,
    ) {
    }

    /**
     * @return array{access: string, expiresAt: \DateTimeImmutable}
     */
    public function createAccessToken(Share $share): array
    {
        $expiresAt = time() + self::ACCESS_TTL;

        return [
            'access' => $this->sign($share, $expiresAt),
            'expiresAt' => new \DateTimeImmutable('@'.$expiresAt),
        ];
    }

    public function isAccessTokenValid(Share $share, ?string $access): bool
    {
        if (!$share->getHasPassword() || null === $access) {
            return false;
        }

        $parts = explode('.', $access, 2);
        if (2 !== count($parts) || !ctype_digit($parts[0])) {
            return false;
        }

        $expiresAt = (int) $parts[0];
        if ($expiresAt <= time()) {
            return false;
        }

        return hash_equals($this->sign($share, $expiresAt), $access);
    }

    /**
     * What the token of the request opens. The owner of the share and the users allowed to
     * share all its assets do not need it (see ShareVoter).
     */
    public function resolveTokenAccess(Share $share, ?Request $request = null): ShareTokenAccessEnum
    {
        $now = new \DateTimeImmutable();
        if (
            !$share->isEnabled()
            || ($share->getExpiresAt() && $share->getExpiresAt() < $now)
            || ($share->getStartsAt() && $share->getStartsAt() > $now)
        ) {
            return ShareTokenAccessEnum::Denied;
        }

        $request ??= $this->requestStack->getCurrentRequest();
        $token = self::getQueryParam($request, self::TOKEN_PARAM);
        if (null === $token || !hash_equals($share->getToken() ?? '', $token)) {
            return ShareTokenAccessEnum::Denied;
        }

        if (!$share->getHasPassword()) {
            return ShareTokenAccessEnum::Granted;
        }

        return $this->isAccessTokenValid($share, self::getQueryParam($request, self::QUERY_PARAM))
            ? ShareTokenAccessEnum::Granted
            : ShareTokenAccessEnum::PasswordRequired;
    }

    public function getRequestAccessToken(): ?string
    {
        return self::getQueryParam($this->requestStack->getCurrentRequest(), self::QUERY_PARAM);
    }

    private static function getQueryParam(?Request $request, string $name): ?string
    {
        $value = $request?->query->all()[$name] ?? null;

        return is_string($value) && '' !== $value ? $value : null;
    }

    private function sign(Share $share, int $expiresAt): string
    {
        $signature = hash_hmac('sha256', $share->getId().'|'.$expiresAt.'|'.$share->getPasswordHash(), $this->secret, true);

        return $expiresAt.'.'.rtrim(strtr(base64_encode($signature), '+/', '-_'), '=');
    }
}
