<?php

declare(strict_types=1);

namespace App\Matomo;

use Symfony\Contracts\HttpClient\Exception\HttpExceptionInterface;
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

        [, $baseId, $recordId] = $regs;

        unset($stat['label']);
        unset($stat['idsubdatatable']);

        try {
            $res = $this->client->request('PATCH', sprintf('/api/v3/records/%s/%s/', $baseId, $recordId), [
                'headers' => [
                    'Authorization' => 'OAuth '.$this->authToken,
                ],
                'json' => [
                    'metadatas' => [
                        [
                            'field_name' => 'MatomoMediaMetrics',
                            'value' => \GuzzleHttp\json_encode($stat)
                        ]
                    ]
                ]
            ]);
            $code = $res->getStatusCode();
            if (in_array($code, [200, 404], true)) {
                return;
            }

            throw new \Exception(sprintf('Got invalid HTTP response code %d: %s', $code, $res->getContent(false)));
        } catch (HttpExceptionInterface $e) {
            if (404 !== $e->getResponse()->getStatusCode()) {
                return;
            }

            throw $e;
        }
    }
}
