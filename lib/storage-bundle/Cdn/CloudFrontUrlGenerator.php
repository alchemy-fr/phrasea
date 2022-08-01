<?php

declare(strict_types=1);

namespace Alchemy\StorageBundle\Cdn;

use Aws\CloudFront\CloudFrontClient;

class CloudFrontUrlGenerator
{
    private ?string $cloudFrontUrl;
    private ?string $cloudFrontPrivateKey;
    private CloudFrontClient $cloudFrontClient;
    private ?string $cloudFrontKeyPairId;
    private int $ttl;

    public function __construct(
        CloudFrontClient $cloudFrontClient,
        int $ttl,
        ?string $cloudFrontUrl = null,
        ?string $cloudFrontPrivateKey = null,
        ?string $cloudFrontKeyPairId = null)
    {
        $this->cloudFrontClient = $cloudFrontClient;
        $this->cloudFrontUrl = $cloudFrontUrl;
        if ($cloudFrontPrivateKey && strpos($cloudFrontPrivateKey, '-----BEGIN ') === false) {
            $cloudFrontPrivateKey = sprintf("-----BEGIN RSA PRIVATE KEY-----\n%s\n-----END RSA PRIVATE KEY-----\n", $cloudFrontPrivateKey);
        }
        $cloudFrontPrivateKey = str_replace('\n', "\n", $cloudFrontPrivateKey);
        $this->cloudFrontPrivateKey = $cloudFrontPrivateKey;
        $this->cloudFrontKeyPairId = $cloudFrontKeyPairId;
        $this->ttl = $ttl;
    }

    public function isEnabled(): bool
    {
        return !empty($this->cloudFrontUrl);
    }

    public function getSignedUrl(string $path, bool $download = false): string
    {
        $url = $this->cloudFrontUrl.'/'.$path;

        if ($download) {
            $url .= '?response-content-disposition=attachment';
        }

        return $this->cloudFrontClient->getSignedUrl([
            'url' => $url,
            'expires' => time() + $this->ttl,
            'private_key' => $this->cloudFrontPrivateKey,
            'key_pair_id' => $this->cloudFrontKeyPairId,
        ]);
    }
}
