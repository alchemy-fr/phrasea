<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Integration\IntegrationInterface;
use App\Integration\IntegrationManager;
use App\Integration\IntegrationRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class IntegrationHelpController extends AbstractController
{
    public function __construct(private readonly IntegrationRegistry $integrationRegistry, private readonly IntegrationManager $integrationManager)
    {
    }

    /**
     * @Route("/admin/integrations/help", name="admin_integrations_help")
     */
    public function __invoke(): Response
    {
        $integrations = array_map(fn (IntegrationInterface $integration): array => [
            'name' => $integration::getName(),
            'title' => $integration::getTitle(),
            'reference' => $this->integrationManager->getIntegrationReference($integration),
        ], $this->integrationRegistry->getIntegrations());

        return $this->render('admin/integration_help.html.twig', [
            'integrations' => $integrations,
        ]);
    }
}
