<?php

declare(strict_types=1);

namespace App\Service\Matomo;

use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final readonly class MatomoManager
{
    public function __construct(
        private HttpClientInterface $matomoClient,
        private CacheInterface $analyticsCache,
        private string $matomoSiteId,
        private string $matomoAuthToken,
    ) {
    }

    public function getMediaMetrics(string $trackingId, string $type): array
    {
        return $this->analyticsCache->get('metrics_'.$trackingId, function (ItemInterface $item) use ($trackingId, $type): array {
            $item->expiresAfter(300);

            if (str_starts_with($type, 'video/') || str_starts_with($type, 'audio/')) {
                if (str_starts_with($type, 'video/')) {
                    $method = 'MediaAnalytics.getVideoTitles';
                } else {
                    $method = 'MediaAnalytics.getAudioTitles';
                }

            } else {
                $method = 'Contents.getContentNames';
            }

            $response = $this->matomoClient->request('POST', '/', [
                'query' => [
                    'module' => 'API',
                    'idSite' => $this->matomoSiteId,
                    'method' => $method,
                    'format' => 'JSON',
                    'date' => '2025-12-01,'.date('Y-m-d', strtotime('+1 day')),
                    'period' => 'range',
                    'label' => $trackingId,
                ],
                'body' => [
                    'token_auth' => $this->matomoAuthToken,
                ],
            ]);

            $data = $response->toArray();

            if (($data['result'] ?? null) === 'error') {
                throw new \Exception(sprintf('Matomo error: %s', $data['message'] ?? 'unknown error'));
            }

            if (empty($data[0])) {
                return [];
            }

            unset($data[0]['idsubdatatable']);
            unset($data[0]['segment']);
            unset($data[0]['label']);

            return $data[0];
        });
    }
}
