<?php

declare(strict_types=1);

namespace App\Controller\Integration;

use App\Integration\Aws\Transcribe\Consumer\AwsTranscribeEventHandler;
use Arthem\Bundle\RabbitBundle\Producer\EventProducer;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route(path: '/integrations/aws-transcribe', name: 'integration_aws_transcribe_')]
class AwsTranscribeIntegrationController extends AbstractController
{
    public function __construct(private readonly EventProducer $eventProducer)
    {
    }

    #[Route(path: '/{integrationId}/events', methods: ['POST'], name: 'event')]
    public function incomingEventAction(
        string $integrationId,
        Request $request
    ): Response {
        $this->eventProducer->publish(AwsTranscribeEventHandler::createEvent($integrationId, $request->getContent()));

        return new Response();
    }
}
