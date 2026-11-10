<?php

declare(strict_types=1);

namespace App\Security;

use Alchemy\AuthBundle\Security\UriJwtManager;
use Alchemy\AuthBundle\Security\Voter\AbstractVoter;
use App\Entity\Publication;
use App\Security\Voter\PublicationVoter;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Contracts\Service\ResetInterface;

/**
 * Applies the download settings of a publication (enabled, via email, download terms).
 *
 * A direct download (asset, rendition, archive) needs a URL signed with the `dl` claim:
 * `direct` for the URLs given by the API when the reader may download right away (download
 * terms accepted), `email` for the links sent by email.
 */
final class PublicationDownloadPolicy implements ResetInterface
{
    public const string CLAIM = 'dl';
    public const string CLAIM_DIRECT = 'direct';
    public const string CLAIM_EMAIL = 'email';

    /**
     * Lifetime of the URL given once the download terms are accepted.
     */
    public const int ACCEPTED_TERMS_URL_TTL = 300;

    /**
     * Mode of each publication for the current token: every asset of a publication asks it again.
     *
     * @var \WeakMap<Publication, DownloadMode>
     */
    private \WeakMap $modes;
    private ?TokenInterface $modesToken = null;

    public function __construct(
        private readonly Security $security,
        private readonly UriJwtManager $uriJwtManager,
    ) {
        $this->modes = new \WeakMap();
    }

    public function reset(): void
    {
        $this->modes = new \WeakMap();
        $this->modesToken = null;
    }

    public function getMode(Publication $publication): DownloadMode
    {
        $token = $this->security->getToken();
        if ($token !== $this->modesToken) {
            $this->reset();
            $this->modesToken = $token;
        }

        return $this->modes[$publication] ??= $this->resolveMode($publication);
    }

    public function denyDirectDownloadUnlessGranted(Publication $publication, Request $request): void
    {
        $mode = $this->getMode($publication);
        if (DownloadMode::Editor === $mode) {
            return;
        }
        if (DownloadMode::None === $mode) {
            throw new AccessDeniedHttpException('Download is disabled for this publication.');
        }

        $jwt = $request->query->get('jwt');
        if (!is_string($jwt) || '' === $jwt) {
            throw new AccessDeniedHttpException('A signed download URL is required.');
        }

        $claim = $this->uriJwtManager->validateUriToken($request->getUri(), $jwt)->claims()->get(self::CLAIM);
        $allowedClaims = DownloadMode::Email === $mode ? [self::CLAIM_EMAIL] : [self::CLAIM_DIRECT, self::CLAIM_EMAIL];
        if (!in_array($claim, $allowedClaims, true)) {
            throw new AccessDeniedHttpException(DownloadMode::Email === $mode ? 'This publication is downloaded via email only.' : 'This URL does not allow downloading.');
        }
    }

    /**
     * A download link sent by email.
     */
    public function denyEmailRequestUnlessGranted(Publication $publication, Request $request): void
    {
        $mode = $this->getMode($publication);
        if (DownloadMode::Editor === $mode) {
            return;
        }
        if (DownloadMode::None === $mode) {
            throw new AccessDeniedHttpException('Download is disabled for this publication.');
        }

        $this->denyUnlessTermsAccepted($publication, $request);
    }

    /**
     * A direct download URL, given in exchange of the acceptance of the download terms.
     */
    public function denyDownloadUrlUnlessGranted(Publication $publication, Request $request): void
    {
        switch ($this->getMode($publication)) {
            case DownloadMode::Editor:
            case DownloadMode::Direct:
                return;
            case DownloadMode::None:
                throw new AccessDeniedHttpException('Download is disabled for this publication.');
            case DownloadMode::Email:
                throw new BadRequestHttpException('This publication is downloaded via email only.');
            case DownloadMode::Terms:
                $this->denyUnlessTermsAccepted($publication, $request);
        }
    }

    public function signDirectDownloadUri(string $uri, ?int $ttl = null): string
    {
        return $this->uriJwtManager->signUri($uri, $ttl, [self::CLAIM => self::CLAIM_DIRECT]);
    }

    public function signEmailDownloadUri(string $uri, int $ttl): string
    {
        return $this->uriJwtManager->signUri($uri, $ttl, [self::CLAIM => self::CLAIM_EMAIL]);
    }

    public static function isTermsEnabled(Publication $publication): bool
    {
        return true === $publication->getDownloadTerms()->getEnabled();
    }

    private function denyUnlessTermsAccepted(Publication $publication, Request $request): void
    {
        if (self::isTermsEnabled($publication) && true !== ($request->request->all()['acceptTerms'] ?? null)) {
            throw new BadRequestHttpException('The download terms must be accepted.');
        }
    }

    private function resolveMode(Publication $publication): DownloadMode
    {
        return match (true) {
            $this->security->isGranted(AbstractVoter::EDIT, $publication) => DownloadMode::Editor,
            !$publication->isDownloadEnabled(),
            !$this->security->isGranted(PublicationVoter::READ_DETAILS, $publication) => DownloadMode::None,
            $publication->isDownloadViaEmail() => DownloadMode::Email,
            self::isTermsEnabled($publication) => DownloadMode::Terms,
            default => DownloadMode::Direct,
        };
    }
}
