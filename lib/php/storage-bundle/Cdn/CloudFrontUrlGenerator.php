<?php

declare(strict_types=1);

namespace Alchemy\StorageBundle\Cdn;

use Alchemy\StorageBundle\Storage\InlineContentTypePolicy;
use Aws\CloudFront\CloudFrontClient;

class CloudFrontUrlGenerator
{
    private ?string $cloudFrontUrl;
    private ?string $cloudFrontPrivateKey;
    private CloudFrontClient $cloudFrontClient;
    private ?string $cloudFrontKeyPairId;
    private int $ttl;
    private string $pathPrefix;

    public function __construct(
        CloudFrontClient $cloudFrontClient,
        int $ttl,
        ?string $cloudFrontUrl = null,
        ?string $cloudFrontPrivateKey = null,
        ?string $cloudFrontKeyPairId = null,
        string $pathPrefix = '',
    ) {
        $this->cloudFrontClient = $cloudFrontClient;
        $this->cloudFrontUrl = $cloudFrontUrl;
        if ($cloudFrontPrivateKey && false === strpos($cloudFrontPrivateKey, '-----BEGIN ')) {
            $cloudFrontPrivateKey = sprintf("-----BEGIN RSA PRIVATE KEY-----\n%s\n-----END RSA PRIVATE KEY-----\n", $cloudFrontPrivateKey);
            $cloudFrontPrivateKey = str_replace('\n', "\n", $cloudFrontPrivateKey);
        }
        $this->cloudFrontPrivateKey = $cloudFrontPrivateKey;
        $this->cloudFrontKeyPairId = $cloudFrontKeyPairId;
        $this->ttl = $ttl;
        $this->pathPrefix = $pathPrefix;
    }

    public function isEnabled(): bool
    {
        return !empty($this->cloudFrontUrl);
    }

    /**
     * Same options as UrlSigner::getSignedUrl(). The response headers are passed as
     * response-content-* query parameters: the distribution must forward them to
     * S3 and include them in its cache key (see doc/tech/Storage/02_cloudfront.md).
     */
    public function getSignedUrl(string $path, array $options = []): string
    {
        $url = $this->cloudFrontUrl.'/'.$this->pathPrefix.$path;

        $headers = InlineContentTypePolicy::getResponseHeaders(
            $options['type'] ?? null,
            basename($path),
            ($options['inline'] ?? false) && !($options['download'] ?? false),
        );
        $query = [
            'response-content-type' => $headers['contentType'],
        ];
        if (null !== $headers['contentDisposition']) {
            $query['response-content-disposition'] = $headers['contentDisposition'];
        }
        $url .= '?'.http_build_query($query, '', '&', PHP_QUERY_RFC3986);

        return $this->cloudFrontClient->getSignedUrl([
            'url' => $url,
            'expires' => time() + ($options['ttl'] ?? $this->ttl),
            'private_key' => $this->cloudFrontPrivateKey,
            'key_pair_id' => $this->cloudFrontKeyPairId,
        ]);
    }
}
