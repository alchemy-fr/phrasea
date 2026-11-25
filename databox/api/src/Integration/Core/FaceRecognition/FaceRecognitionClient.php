<?php

declare(strict_types=1);

namespace App\Integration\Core\FaceRecognition;

use Alchemy\CoreBundle\Listener\ClientExceptionListener;
use App\Service\Fixture\FixtureAnalysisCache;
use Symfony\Component\Mime\Part\DataPart;
use Symfony\Component\Mime\Part\Multipart\FormDataPart;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * HTTP client of the self-hosted face-recognition service (see infra/docker/face-recognition).
 */
class FaceRecognitionClient
{
    public function __construct(
        private readonly HttpClientInterface $faceRecognitionClient,
        private readonly ClientExceptionListener $clientExceptionListener,
        private readonly FixtureAnalysisCache $fixtureAnalysisCache,
    ) {
    }

    /**
     * @return array{
     *     model: string,
     *     dim: int,
     *     width: int,
     *     height: int,
     *     faces: array<array{
     *         box: array{x: float, y: float, w: float, h: float},
     *         confidence: float,
     *         landmarks?: array<array{0: float, 1: float}>,
     *         embedding: float[],
     *         age?: int,
     *         gender?: string,
     *     }>,
     * }
     */
    public function detectFaces(string $path): array
    {
        return $this->fixtureAnalysisCache->remember('faces', $path, function () use ($path): array {
            // The multipart body is built in memory (the image is downscaled to a few MB): curl can only
            // resend a string body when a kept-alive connection closed by the service must be retried.
            $formData = new FormDataPart([
                'file' => DataPart::fromPath($path),
            ]);

            return $this->clientExceptionListener->wrapClientRequest(fn (): array => $this->faceRecognitionClient->request('POST', '/detect', [
                'headers' => $formData->getPreparedHeaders()->toArray(),
                'body' => $formData->bodyToString(),
                'timeout' => 120,
            ])->toArray());
        });
    }
}
