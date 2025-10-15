<?php

declare(strict_types=1);

namespace App\Controller\Integration;

use App\Integration\IntegrationManager;
use App\Integration\Phrasea\Uploader\Message\IngestUploaderCommit;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
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

        $commitId = $data['commit_id'] ?? null;
        if (!$commitId) {
            throw new BadRequestHttpException('Missing commit_id');
        }
        $token = $data['token'] ?? null;
        if (!$token) {
            throw new BadRequestHttpException('Missing token');
        }

        $bus->dispatch(new IngestUploaderCommit(
            $integrationId,
            $commitId,
            $token
        ));

        return new Response();
    }
}
