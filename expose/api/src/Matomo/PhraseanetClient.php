<?php

declare(strict_types=1);

namespace App\Matomo;

use Symfony\Contracts\HttpClient\HttpClientInterface;

final class PhraseanetClient
{
    private HttpClientInterface $client;
    private string $authToken;

    public function __construct(
        HttpClientInterface $phraseanetClient,
        string $phraseanetAuthToken
    )
    {
        $this->client = $phraseanetClient;
        $this->authToken = $phraseanetAuthToken;
    }

    public function patchField(array $stat): void
    {
        if (0 === preg_match('#^(?:\./)?\w+_(\d+)_(\d+)$#', $stat['label'], $regs)) {
            return;
        }

        list(, $baseId, $recordId) = $regs;

        unset($stat['label']);
        unset($stat['idsubdatatable']);

        $this->client->request('GET', sprintf('/records/%s/%s', $baseId, $recordId), [
            'json' => [
                'metadatas' => [
                    [
                        'field_name' => 'matomo_media_metrics',
                        'value' => \GuzzleHttp\json_encode($stat)
                    ]
                ]
            ]
        ]);
    }
}
