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
    ) {
        $this->client = $phraseanetClient;
        $this->authToken = $phraseanetAuthToken;
    }

    public function patchField(array $stat): void
    {
        if (0 === preg_match('#^(?:\./)?\w+_(\d+)_(\d+)$#', $stat['label'], $regs)) {
            return;
        }

        [, $databoxId, $recordId] = $regs;

        unset($stat['label']);
        unset($stat['idsubdatatable']);

        try {
            $data = $this->client->request('GET', sprintf('/api/v1/records/%s/%s/metadatas/', $databoxId, $recordId), [
                'headers' => [
                    'Authorization' => 'OAuth '.$this->authToken,
                ],
            ])->toArray();
        } catch (HttpExceptionInterface $e) {
            if (404 === $e->getResponse()->getStatusCode()) {
                return;
            }

            throw $e;
        }

        $currentAttr = [];
        foreach ($data['response']['record_metadatas'] as $meta) {
            if ('MatomoMediaMetrics' === $meta['name']) {
                $currentAttr = json_decode($meta['value'], true);
                break;
            }
        }

        if (!shouldUpdate($currentAttr, $stat)) {
            return;
        }

        $res = $this->client->request('PATCH', sprintf('/api/v3/records/%s/%s/', $databoxId, $recordId), [
            'headers' => [
                'Authorization' => 'OAuth '.$this->authToken,
            ],
            'json' => [
                'metadatas' => [
                    [
                        'field_name' => 'MatomoMediaMetrics',
                        'value' => json_encode($stat, JSON_THROW_ON_ERROR),
                    ],
                ],
            ],
        ]);

        $code = $res->getStatusCode();
        if (200 !== $code) {
            throw new \Exception(sprintf('Got invalid HTTP response code %d: %s', $code, $res->getContent(false)));
        }
    }
}

function shouldUpdate(array $current, array $new): bool
{
    if (empty($current)) {
        return true;
    }

    if (count($new) !== count($current)) {
        return true;
    }

    foreach ($new as $k => $v) {
        if (!isset($current[$k]) || $current[$k] !== $v) {
            return true;
        }
    }

    return false;
}
