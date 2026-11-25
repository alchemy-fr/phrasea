<?php

declare(strict_types=1);

namespace App\Controller\Integration;

use App\Integration\Phrasea\Uploader\Message\IngestUploaderCommit;
use App\Integration\Phrasea\Uploader\UploaderIntegrationKeyChecker;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;

#[Route(path: '/integrations/uploader', name: 'integration_uploader_')]
class UploaderIntegrationController extends AbstractController
{
    #[Route(path: '/{integrationId}/incoming-commit', name: 'incoming_commit', methods: ['POST'])]
    public function incomingRenditionAction(
        string $integrationId,
        Request $request,
        MessageBusInterface $bus,
        UploaderIntegrationKeyChecker $keyChecker,
    ): Response {
        $keyChecker->authenticate($integrationId, $request);

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
