<?php

declare(strict_types=1);

namespace App\Controller\Matomo;

use App\Service\Matomo\MatomoManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class MatomoController extends AbstractController
{
    #[Route(path: '/matomo/stats/{trackingId}', name: 'matomo_get_stats', methods: ['GET'])]
    public function getStatsAction(
        string $trackingId,
        Request $request,
        MatomoManager $matomoManager,
    ): Response {
        $data = $matomoManager->getStats($trackingId, $request->query->get('type', ''));

        return new JsonResponse($data[0] ?? null);
    }
}
