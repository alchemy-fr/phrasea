<?php

declare(strict_types=1);

namespace App\Controller\Integration;

use App\Integration\Aws\Transcribe\Consumer\AwsTranscribeEvent;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Annotation\Route;

#[Route(path: '/integrations/aws-transcribe', name: 'integration_aws_transcribe_')]
class AwsTranscribeIntegrationController extends AbstractController
{
    public function __construct(private readonly MessageBusInterface $bus)
    {
    }

    #[Route(path: '/{integrationId}/events', name: 'event', methods: ['POST'])]
    public function incomingEventAction(
        string $integrationId,
        Request $request,
    ): Response {
        $this->bus->dispatch(new AwsTranscribeEvent($integrationId, $request->getContent()));

        return new Response();
    }
}
