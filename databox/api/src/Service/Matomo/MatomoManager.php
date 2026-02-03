<?php

declare(strict_types=1);

namespace App\Service\Matomo;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
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
        #[Autowire(env: 'bool:MATOMO_MEDIA_PLUGIN_ENABLED')]
        private bool $mediaAnalyticsEnabled = false,
    ) {
    }

    public function getMediaMetrics(string $trackingId, string $type): array
    {
        return $this->analyticsCache->get('metrics_'.$trackingId, function (ItemInterface $item) use (
            $trackingId,
            $type
        ): array {
            $item->expiresAfter(300);

            if (!$this->matomoAuthToken) {
                throw new \Exception('Matomo auth token is not configured');
            }

            $method = 'Contents.getContentPieces';

            if ($this->mediaAnalyticsEnabled) {
                $isVideo = str_starts_with($type, 'video/');
                if ($isVideo || str_starts_with($type, 'audio/')) {
                    if ($isVideo) {
                        $method = 'MediaAnalytics.getGroupedVideoResources';
                    } else {
                        $method = 'MediaAnalytics.getGroupedAudioResources';
                    }
                }
            }

            $response = $this->matomoClient->request('POST', '/', [
                'query' => [
                    'module' => 'API',
                    'idSite' => $this->matomoSiteId,
                    'method' => $method,
                    'format' => 'JSON',
                    'date' => '2000-12-01,'.date('Y-m-d', strtotime('+1 day')),
                    'period' => 'range',
                    'filter_pattern' => $trackingId,
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
