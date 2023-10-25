<?php

declare(strict_types=1);

namespace App\Matomo;

use Symfony\Contracts\HttpClient\HttpClientInterface;

final class MatomoClient
{
    private HttpClientInterface $client;
    private string $matomoSiteId;
    private string $authToken;

    public function __construct(
        HttpClientInterface $matomoClient,
        string $matomoSiteId,
        string $matomoAuthToken
    )
    {
        $this->client = $matomoClient;
        $this->matomoSiteId = $matomoSiteId;
        $this->authToken = $matomoAuthToken;
    }

    public function getStats(int $offset = 0, int $limit = 100): array
    {
        $response = $this->client->request('GET', '/', [
            'query' => [
                'module' => 'API',
                'idSite' => $this->matomoSiteId,
                'method' => 'MediaAnalytics.getGroupedVideoResources',
                'format' => 'JSON',
                'token_auth' => $this->authToken,
                'date' => '2020-01-01,'.date('Y-m-d'),
                'period' => 'range',
                'filter_offset' => $offset,
                'filter_limit' => $limit,
            ]
        ]);

        return $response->toArray();
    }
}
