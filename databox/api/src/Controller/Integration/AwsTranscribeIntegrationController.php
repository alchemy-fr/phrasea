<?php

declare(strict_types=1);

namespace App\Controller\Integration;

use App\Integration\Aws\Sns\SnsCertificateClient;
use App\Integration\Aws\Transcribe\Consumer\AwsTranscribeEvent;
use Aws\Sns\Message;
use Aws\Sns\MessageValidator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface as HttpClientExceptionInterface;

#[Route(path: '/integrations/aws-transcribe', name: 'integration_aws_transcribe_')]
class AwsTranscribeIntegrationController extends AbstractController
{
    public function __construct(
        private readonly MessageBusInterface $bus,
        private readonly SnsCertificateClient $snsCertificateClient,
    ) {
    }

    /**
     * Public endpoint: only the messages signed by AWS SNS are accepted.
     */
    #[Route(path: '/{integrationId}/events', name: 'event', methods: ['POST'])]
    public function incomingEventAction(
        string $integrationId,
        Request $request,
    ): Response {
        $body = $request->getContent();
        $this->validateSnsMessage($body);

        $this->bus->dispatch(new AwsTranscribeEvent($integrationId, $body));

        return new Response();
    }

    private function validateSnsMessage(string $body): void
    {
        try {
            $message = Message::fromJsonString($body);
            new MessageValidator($this->snsCertificateClient)->validate($message);
        } catch (\RuntimeException|\InvalidArgumentException|HttpClientExceptionInterface $e) {
            // Aws\Sns\Exception\InvalidSnsMessageException is a \RuntimeException
            throw new AccessDeniedHttpException('Invalid SNS message', $e);
        }
    }
}
