<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Asset;
use App\Security\Voter\AssetVoter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;

#[Route(path: '/publications/{id}/vtt/{vttId}.vtt', name: 'asset_webvtt')]
final class GetAssetWebVTTAction extends AbstractController
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    public function __invoke(string $id, string $vttId, Request $request): Response
    {
        $corsHeaders = [
            'Access-Control-Allow-Origin' => '*',
            'Access-Control-Allow-Credentials' => 'true',
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
        $options = [
            's_maxage' => 86400,
            'max_age' => 86400,
            'public' => true,
        ];

        $response->setCache($options);

        return $response;
    }
}
