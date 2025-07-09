<?php

declare(strict_types=1);

namespace App\Controller\Integration;

use App\Border\Model\Upload\IncomingUpload;
use App\Integration\IntegrationManager;
use App\Integration\Phrasea\Uploader\Message\IngestUploaderCommit;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Annotation\Route;

#[Route(path: '/integrations/uploader', name: 'integration_uploader_')]
class UploaderIntegrationController extends AbstractController
{
    #[Route(path: '/{integrationId}/incoming-commit', name: 'incoming_commit', methods: ['POST'])]
    public function incomingRenditionAction(
        string $integrationId,
        Request $request,
        MessageBusInterface $bus,
        IntegrationManager $integrationManager,
    ): Response {
        $authToken = preg_replace('#^ApiKey\s+#', '', $request->headers->get('Authorization', ''));
        if (!$authToken) {
            throw new UnauthorizedHttpException('Missing ApiKey token');
        }

        $integration = $integrationManager->loadIntegration($integrationId);
        $config = $integrationManager->getIntegrationConfiguration($integration);
        if ($config['securityKey'] !== $authToken) {
            throw new AccessDeniedHttpException('Invalid token');
        }

        $data = json_decode($request->getContent(), true);
        $incomingUpload = IncomingUpload::fromArray($data);

        $bus->dispatch(new IngestUploaderCommit($integrationId, $incomingUpload->commit_id, $incomingUpload->token));

        return new Response();
    }
}
