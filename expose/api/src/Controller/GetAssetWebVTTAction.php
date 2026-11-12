<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Asset;
use App\Http\Cache\PublicHttpCachePolicy;
use App\Security\Voter\AssetVoter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

#[Route(path: '/publications/{id}/vtt/{vttId}.vtt', name: Asset::GET_WEBVTT_ROUTE_NAME)]
final class GetAssetWebVTTAction extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly PublicHttpCachePolicy $cachePolicy,
    ) {
    }

    public function __invoke(string $id, string $vttId, Request $request): Response
    {
        $corsHeaders = [
            'Access-Control-Allow-Origin' => '*',
            'Access-Control-Allow-Methods' => 'GET, OPTIONS',
            'Access-Control-Allow-Headers' => 'Origin, X-Requested-With, Content-Type, Accept',
        ];
        if ('OPTIONS' === $request->getMethod()) {
            return new Response('', 204, [...$corsHeaders, 'Content-Type' => 'text/plain charset=UTF-8', 'Content-Length' => 0]);
        }

        /** @var Asset|null $asset */
        $asset = $this->em
            ->getRepository(Asset::class)
            ->find($id);

        if (!$asset instanceof Asset) {
            throw new NotFoundHttpException();
        }

        $this->denyAccessUnlessGranted(AssetVoter::READ, $asset);

        $webVTT = $asset->getWebVTTById($vttId);
        if (null === $webVTT) {
            throw new NotFoundHttpException(sprintf('WebVTT "%s" not found.', $vttId));
        }

        $response = new Response($webVTT['content'], 200, [...$corsHeaders, 'Content-Type' => 'text/vtt']);

        $publication = $asset->getPublication();
        if ($this->cachePolicy->isCacheable($request, $publication)) {
            $this->cachePolicy->setPublic($response, $publication);
        } else {
            $this->cachePolicy->setPrivate($response);
        }

        return $response;
    }
}
