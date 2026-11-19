<?php

declare(strict_types=1);

namespace App\Controller;

use Alchemy\AuthBundle\Security\UriJwtManager;
use Alchemy\CoreBundle\Util\UuidUtil;
use App\Entity\Publication;
use App\Http\Cache\PublicHttpCachePolicy;
use App\Security\Voter\PublicationVoter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

/**
 * The stylesheet is loaded by a <link> tag, which sends no credentials: the CSS of a
 * publication hidden from anonymous readers is reached through the signed URL that
 * PublicationNormalizer gives to the users who may read it.
 */
#[Route(path: '/publications/{id}/style.{hash}.css', name: 'publication_css')]
final class GetPublicationCssAction extends AbstractController
{
    /**
     * The URL changes with the CSS (its hash), so browsers may keep it long.
     */
    private const int BROWSER_MAX_AGE = 7_776_000;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly UriJwtManager $uriJwtManager,
        private readonly PublicHttpCachePolicy $cachePolicy,
    ) {
    }

    public function __invoke(Request $request, string $id, string $hash): Response
    {
        $publication = UuidUtil::isValid($id) ? $this->em->find(Publication::class, $id) : null;

        // A publication the user may not read answers as an unknown one.
        if (!$publication instanceof Publication
            || (!$this->isGranted(PublicationVoter::READ, $publication) && !$this->hasValidSignature($request))) {
            throw new NotFoundHttpException();
        }

        $response = new Response($publication->getCss(), 200, [
            'Content-Type' => 'text/css',
        ]);

        if ($publication->isVisible() && !$request->query->has('jwt')) {
            $visibleUntil = $publication->getVisibleUntil();
            $visibleFor = null === $visibleUntil ? PHP_INT_MAX : max(0, $visibleUntil->getTimestamp() - time());

            // Not purged by ProxyCachePurger: the proxy keeps it no longer than the purged routes.
            $response->setCache([
                'public' => true,
                'max_age' => min(self::BROWSER_MAX_AGE, $visibleFor),
                's_maxage' => min(PublicHttpCachePolicy::MAX_AGE, $visibleFor),
            ]);
        } else {
            $this->cachePolicy->setPrivate($response);
        }

        return $response;
    }

    private function hasValidSignature(Request $request): bool
    {
        $jwt = $request->query->get('jwt');
        if (!is_string($jwt) || '' === $jwt) {
            return false;
        }

        try {
            $this->uriJwtManager->validateUri($request->getUri(), $jwt);
        } catch (AccessDeniedHttpException) {
            return false;
        }

        return true;
    }
}
