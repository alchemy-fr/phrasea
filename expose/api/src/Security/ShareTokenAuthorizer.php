<?php

declare(strict_types=1);

namespace App\Security;

use App\Entity\Publication;
use App\Entity\PublicationShare;
use App\Repository\PublicationShareRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Grants access to the publications shared with the tokens of the `X-Share-Tokens`
 * header (comma separated): each active share opens its own publication only, not its
 * parents nor its children.
 */
final readonly class ShareTokenAuthorizer
{
    public const string HEADER_NAME = 'X-Share-Tokens';
    public const int MAX_TOKENS = 20;

    /**
     * Request attribute holding the ids of the publications shared with the tokens of the
     * request: the voter is called many times per request.
     */
    private const string SHARED_PUBLICATIONS_ATTRIBUTE = '_shared_publication_ids';

    public function __construct(
        private RequestStack $requestStack,
        private PublicationShareRepository $shareRepository,
    ) {
    }

    public function grantsAccessTo(Publication $publication): bool
    {
        $request = $this->requestStack->getCurrentRequest();
        if (null === $request) {
            return false;
        }

        return isset($this->getSharedPublicationIds($request)[$publication->getId()]);
    }

    /**
     * @return array<string, true>
     */
    private function getSharedPublicationIds(Request $request): array
    {
        if ($request->attributes->has(self::SHARED_PUBLICATIONS_ATTRIBUTE)) {
            return $request->attributes->get(self::SHARED_PUBLICATIONS_ATTRIBUTE);
        }

        $ids = [];
        $tokens = self::parseHeader($request->headers->get(self::HEADER_NAME));
        foreach ($this->shareRepository->findActiveByTokens($tokens) as $share) {
            // The database may compare strings without case sensitivity
            if (in_array($share->getToken(), $tokens, true)) {
                $ids[$share->getPublication()->getId()] = true;
            }
        }

        $request->attributes->set(self::SHARED_PUBLICATIONS_ATTRIBUTE, $ids);

        return $ids;
    }

    /**
     * @return string[]
     */
    public static function parseHeader(?string $header): array
    {
        if (null === $header || '' === $header) {
            return [];
        }

        $tokens = [];
        foreach (explode(',', $header) as $token) {
            $token = trim($token);
            if ('' !== $token && strlen($token) <= PublicationShare::TOKEN_LENGTH && !in_array($token, $tokens, true)) {
                $tokens[] = $token;
            }
            if (count($tokens) >= self::MAX_TOKENS) {
                break;
            }
        }

        return $tokens;
    }
}
