<?php

declare(strict_types=1);

namespace App\Controller\Integration;

use App\Entity\Core\File;
use App\Integration\IntegrationManager;
use App\Security\Voter\AbstractVoter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;

class AssetActionIntegrationController extends AbstractController
{
    #[Route(path: '/integrations/{integrationId}/files/{fileId}/actions/{action}', name: 'integration_asset_action', methods: ['POST'])]
    public function incomingRenditionAction(
        string $integrationId,
        string $fileId,
        string $action,
        Request $request,
        IntegrationManager $integrationManager,
        EntityManagerInterface $em
    ): Response {
        $wsIntegration = $integrationManager->loadIntegration($integrationId);

        $file = $em->find(File::class, $fileId);
        if (!$file instanceof File) {
            throw new NotFoundHttpException(sprintf('File "%s" not found', $fileId));
        }

        $this->denyAccessUnlessGranted(AbstractVoter::EDIT, $file, sprintf('Not allowed to edit file "%s"', $fileId));

        return $integrationManager->handleFileAction($wsIntegration, $action, $request, $file);
    }
}
