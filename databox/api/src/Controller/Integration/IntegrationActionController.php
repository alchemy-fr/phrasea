<?php

declare(strict_types=1);

namespace App\Controller\Integration;

use App\Integration\IntegrationManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class IntegrationActionController extends AbstractController
{
    #[Route(path: '/integrations/{integrationId}/actions/{action}', name: 'integration_action', methods: ['POST'])]
    public function integrationAction(
        string $integrationId,
        string $action,
        Request $request,
        IntegrationManager $integrationManager,
    ): Response {
        $wsIntegration = $integrationManager->loadIntegration($integrationId);

        return $integrationManager->handleAction($wsIntegration, $action, $request);
    }
}
