<?php

declare(strict_types=1);

namespace App\Service\Face;

use Alchemy\CoreBundle\Listener\ClientExceptionListener;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * HTTP client of the self-hosted face-recognition service (see infra/docker/face-recognition).
 */
class FaceRecognitionClient
{
    public function __construct(
        private readonly HttpClientInterface $faceRecognitionClient,
        private readonly ClientExceptionListener $clientExceptionListener,
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
        return $this->clientExceptionListener->wrapClientRequest(fn (): array => $this->faceRecognitionClient->request('POST', '/detect', [
            'body' => [
                'file' => fopen($path, 'r'),
            ],
            'timeout' => 120,
        ])->toArray());
    }
}
