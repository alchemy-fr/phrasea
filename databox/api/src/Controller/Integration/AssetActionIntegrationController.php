<?php

declare(strict_types=1);

namespace App\Controller\Integration;

use App\Entity\Core\Asset;
use App\Integration\IntegrationManager;
use App\Security\Voter\AssetVoter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;

class AssetActionIntegrationController extends AbstractController
{
    /**
     * @Route(path="/integrations/{integrationId}/assets/{assetId}/actions/{action}", name="integration_asset_action", methods={"POST"})
     */
    public function incomingRenditionAction(
        string $integrationId,
        string $assetId,
        string $action,
        Request $request,
        IntegrationManager $integrationManager,
        EntityManagerInterface $em
    ): Response {
        $wsIntegration = $integrationManager->loadIntegration($integrationId);

        $asset = $em->find(Asset::class, $assetId);
        if (!$asset instanceof Asset) {
            throw new NotFoundHttpException(sprintf('Asset "%s" not found', $assetId));
        }

        $this->denyAccessUnlessGranted(AssetVoter::EDIT, $asset, sprintf('Not allowed to edit asset "%s"', $assetId));

        return $integrationManager->handleAssetAction($wsIntegration, $action, $request, $asset);
    }
}
