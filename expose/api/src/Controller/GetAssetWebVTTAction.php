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

/**
 * @Route("/publications/{id}/vtt/{hash}.vtt", name="asset_webvtt")
 */
final class GetAssetWebVTTAction extends AbstractController
{
    private EntityManagerInterface $em;

    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;
    }

    public function __invoke(string $id, string $hash, Request $request): Response
    {
        $corsHeaders = [
            'Access-Control-Allow-Origin' => '*',
            'Access-Control-Allow-Credentials' => 'true',
            'Access-Control-Allow-Methods' => 'GET, OPTIONS',
            'Access-Control-Allow-Headers' => 'Origin, X-Requested-With, Content-Type, Accept',
        ];
        if ('OPTIONS' === $request->getMethod()) {
            return new Response('', 204, array_merge(
                $corsHeaders,
                [
                    'Content-Type' => 'text/plain charset=UTF-8',
                    'Content-Length' => 0,
                ]
            ));
        }

        /** @var Asset|null $asset */
        $asset = $this->em
            ->getRepository(Asset::class)
            ->find($id);

        if (!$asset instanceof Asset) {
            throw new NotFoundHttpException();
        }

        $this->denyAccessUnlessGranted(AssetVoter::READ, $asset);

        $response = new Response($asset->getWebVTT(), 200, array_merge($corsHeaders, [
            'Content-Type' => 'text/vtt',
        ]));
        $response->setCache([
            's_maxage' => 7776000,
            'max_age' => 7776000,
            'public' => true,
        ]);

        return $response;
    }
}
