<?php

declare(strict_types=1);

namespace App\Service\Matomo;

use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final readonly class MatomoManager
{
    public function __construct(
        private readonly HttpClientInterface $matomoClient,
        private CacheInterface $analyticsCache,
        private string $matomoSiteId,
        private string $matomoAuthToken,
    ) {
    }

    public function getStats($trackingId, $type): array
    {
        return $this->analyticsCache->get('analytics_'.$trackingId, function (ItemInterface $item) use ($trackingId, $type) {
            $item->expiresAfter(300);

            if (str_contains($type, 'video') || str_contains($type, 'audio')) {
                if (str_contains($type, 'video')) {
                    $method = 'MediaAnalytics.getVideoTitles';
                } else {
                    $method = 'MediaAnalytics.getAudioTitles';
                }

                $response = $this->matomoClient->request('GET', '/', [
                    'query' => [
                        'module' => 'API',
                        'idSite' => $this->matomoSiteId,
                        'method' => $method,
                        'format' => 'JSON',
                        'token_auth' => $this->matomoAuthToken,
                        'date' => '2025-12-01,'.date('Y-m-d', strtotime('+1 day')),
                        'period' => 'range',
                        'label' => $trackingId,
                    ],
                ]);
            } else {
                $response = $this->matomoClient->request('GET', '/', [
                    'query' => [
                        'module' => 'API',
                        'idSite' => $this->matomoSiteId,
                        'method' => 'Contents.getContentNames',
                        'format' => 'JSON',
                        'token_auth' => $this->matomoAuthToken,
                        'date' => '2025-12-01,'.date('Y-m-d', strtotime('+1 day')),
                        'period' => 'range',
                        'label' => $trackingId,
                    ],
                ]);
            }

            $stats = $response->toArray();

            unset($stats[0]['idsubdatatable']);
            unset($stats[0]['segment']);
            unset($stats[0]['label']);

            return $stats;
        });
    }
}
