<?php

namespace App\Integration\Phrasea\Expose;

use App\Asset\FileFetcher;
use App\Entity\Core\Asset;
use App\Entity\Integration\IntegrationToken;
use App\Integration\IntegrationConfig;
use App\Integration\Phrasea\PhraseaClientFactory;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final readonly class ExposeClient
{
    public function __construct(
        private PhraseaClientFactory $clientFactory,
        private HttpClientInterface $uploadClient,
        private FileFetcher $fileFetcher,
    ) {
    }

    private function create(IntegrationConfig $config, IntegrationToken $integrationToken): HttpClientInterface
    {
        return $this->clientFactory->create(
            $config['baseUrl'],
            $config['clientId'],
            $integrationToken,
        );
    }

    public function createPublications(IntegrationConfig $config, IntegrationToken $integrationToken, array $data): array
    {
        return $this->create($config, $integrationToken)
            ->request('POST', '/publications', [
                'json' => $data,
            ])
            ->toArray();
    }

    public function deletePublication(IntegrationConfig $config, IntegrationToken $integrationToken, string $id): void
    {
        $this->create($config, $integrationToken)
            ->request('DELETE', '/publications/'.$id)
        ;
    }

    public function getPublication(IntegrationConfig $config, IntegrationToken $integrationToken, string $id): array
    {
        return $this->create($config, $integrationToken)
            ->request('GET', '/publications/'.$id)
            ->toArray()
        ;
    }

    public function postAsset(IntegrationConfig $config, IntegrationToken $integrationToken, string $publicationId, Asset $asset, array $extraData = []): void
    {
        $source = $asset->getSource();
        $data = array_merge([
            'publication_id' => $publicationId,
            'asset_id' => $asset->getId(),
            'title' => $asset->getTitle(),
            'upload' => [
                'type' => $source->getType(),
                'size' => $source->getSize(),
                'name' => $source->getOriginalName(),
            ],
        ], $extraData);

        $pubAsset = $this->create($config, $integrationToken)
            ->request('POST', '/assets', [
                'json' => $data,
            ])
            ->toArray()
        ;

        $uploadUrl = $pubAsset['uploadURL'];

        $fetchedFilePath = $this->fileFetcher->getFile($source);
        try {
            $this->uploadClient->request('PUT', $uploadUrl, [
                'headers' => [
                    'Content-Type' => $source->getType(),
                    'Content-Length' => filesize($fetchedFilePath),
                ],
                'body' => fopen($fetchedFilePath, 'r')
            ]);
        } finally {
            @unlink($fetchedFilePath);
        }
    }

    public function deleteAsset(IntegrationConfig $config, IntegrationToken $integrationToken, string $assetId): void
    {
        $this->create($config, $integrationToken)
            ->request('DELETE', '/assets/'.$assetId)
        ;
    }
}
