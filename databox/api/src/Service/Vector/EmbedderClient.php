<?php

declare(strict_types=1);

namespace App\Service\Vector;

use Alchemy\CoreBundle\Listener\ClientExceptionListener;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final readonly class EmbedderClient
{
    public function __construct(
        private HttpClientInterface $similarityEmbedderClient,
        private ClientExceptionListener $clientExceptionListener,
    ) {
    }

    /**
     * @return array{vector: float[], model: string, dim: int}
     */
    public function embedImageFile(string $path): array
    {
        return $this->clientExceptionListener->wrapClientRequest(fn (): array => $this->similarityEmbedderClient->request('POST', '/embed', [
            'body' => [
                'file' => fopen($path, 'r'),
            ],
            'timeout' => 60,
        ])->toArray());
    }

    /**
     * @return array{vector: float[], model: string, dim: int}
     */
    public function embedText(string $text): array
    {
        return $this->clientExceptionListener->wrapClientRequest(fn (): array => $this->similarityEmbedderClient->request('POST', '/embed-text', [
            'json' => [
                'text' => $text,
            ],
            'timeout' => 60,
        ])->toArray());
    }
}
