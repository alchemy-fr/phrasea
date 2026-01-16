<?php

declare(strict_types=1);

namespace App\Controller\Matomo;

use App\Service\Matomo\MatomoManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class MatomoController extends AbstractController
{
    #[Route(path: '/matomo/getStats/{trackingId}', name: 'matomo_get_stats', methods: ['POST'])]
    public function getStatsAction(
        string $trackingId,
        Request $request,
        MatomoManager $matomoManager,
    ): Response {
        $data = $matomoManager->getStats($trackingId, $request->query->get('type', ''));

        if (!isset($data[0])) {
            return new Response('{}', Response::HTTP_OK);
        }

        return new Response(json_encode($data[0]), Response::HTTP_OK, ['Content-Type' => 'application/json']);
    }
}
