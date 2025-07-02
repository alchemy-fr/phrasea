<?php

declare(strict_types=1);

namespace App\Controller\Integration;

use App\Integration\Phrasea\Uploader\Message\IngestUploaderCommit;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
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
    ): Response {
        $token = $request->request->get('token');
        $commitId = $request->request->get('commit_id');
        if (!$token) {
            throw new UnauthorizedHttpException('Missing token');
        }

        $bus->dispatch(new IngestUploaderCommit($integrationId, $commitId, $token));

        return new Response();
    }
}
