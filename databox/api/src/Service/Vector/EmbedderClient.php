<?php

declare(strict_types=1);

namespace App\Service\Vector;

use Alchemy\CoreBundle\Listener\ClientExceptionListener;
use Symfony\Component\Mime\Part\DataPart;
use Symfony\Component\Mime\Part\Multipart\FormDataPart;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class EmbedderClient
{
    public function __construct(
        private readonly HttpClientInterface $similarityEmbedderClient,
        private readonly ClientExceptionListener $clientExceptionListener,
    ) {
    }

    /**
     * @return array{vector: float[], model: string, dim: int}
     */
    public function embedImageFile(string $path): array
    {
        // The multipart body is built in memory (the image is at most a few MB): curl can only
        // resend a string body when a kept-alive connection closed by the embedder must be retried.
        $formData = new FormDataPart([
            'file' => DataPart::fromPath($path),
        ]);

        return $this->clientExceptionListener->wrapClientRequest(fn (): array => $this->similarityEmbedderClient->request('POST', '/embed', [
            'headers' => $formData->getPreparedHeaders()->toArray(),
            'body' => $formData->bodyToString(),
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
