<?php

declare(strict_types=1);

namespace App\Integration\Matomo;

use App\Integration\IntegrationConfig;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class MatomoProcessor
{
    public function __construct(
        private readonly HttpClientInterface $matomoClient,
        private CacheInterface $analyticsCache,
    ) {
    }

    public function process($trackingId, $type, IntegrationConfig $config): array
    {
        $baseUrl = $config['matomoUrl'];
        $matomoSiteId = $config['matomoSiteId'];
        $matomoAuthToken = $config['matomoAuthToken'];

        if (empty($baseUrl) || empty($matomoSiteId) || empty($matomoAuthToken)) {
            throw new \InvalidArgumentException('Matomo configuration is incomplete.');
        }

        return $this->analyticsCache->get('analytics_'.$trackingId, function (ItemInterface $item) use ($trackingId, $type, $baseUrl, $matomoSiteId, $matomoAuthToken) {
            $item->expiresAfter(300);

            if (false !== strpos($type, 'video') || false !== strpos($type, 'audio')) {
                if (false !== strpos($type, 'video')) {
                    $method = 'MediaAnalytics.getVideoTitles';
                } else {
                    $method = 'MediaAnalytics.getAudioTitles';
                }

                $response = $this->matomoClient->request('GET', $baseUrl, [
                    'query' => [
                        'module' => 'API',
                        'idSite' => $matomoSiteId,
                        'method' => $method,
                        'format' => 'JSON',
                        'token_auth' => $matomoAuthToken,
                        'date' => '2025-12-01,'.date('Y-m-d', strtotime('+1 day')),
                        'period' => 'range',
                        'label' => $trackingId,
                    ],
                ]);
            } else {
                $response = $this->matomoClient->request('GET', $baseUrl, [
                    'query' => [
                        'module' => 'API',
                        'idSite' => $matomoSiteId,
                        'method' => 'Contents.getContentNames',
                        'format' => 'JSON',
                        'token_auth' => $matomoAuthToken,
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
