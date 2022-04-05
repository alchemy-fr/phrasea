<?php

declare(strict_types=1);

namespace App\Security;

use App\Entity\Asset;
use App\Entity\MediaInterface;
use App\Entity\PublicationAsset;
use App\Entity\SubDefinition;
use Arthem\RequestSignerBundle\RequestSigner;
use Aws\CloudFront\CloudFrontClient;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

class AssetUrlGenerator
{
    private string $storageBaseUrl;
    private RequestSigner $requestSigner;
    private RequestStack $requestStack;
    private ?string $cloudFrontUrl;
    private ?string $cloudFrontPrivateKey;
    private CloudFrontClient $cloudFrontClient;
    private ?string $cloudFrontKeyPairId;
    private int $requestSignatureTtl;

    public function __construct(
        string $storageBaseUrl,
        RequestSigner $requestSigner,
        RequestStack $requestStack,
        CloudFrontClient $cloudFrontClient,
        int $requestSignatureTtl,
        ?string $cloudFrontUrl = null,
        ?string $cloudFrontPrivateKey = null,
        ?string $cloudFrontKeyPairId = null
    )
    {
        $this->storageBaseUrl = $storageBaseUrl;
        $this->requestSigner = $requestSigner;
        $this->requestStack = $requestStack;
        $this->cloudFrontClient = $cloudFrontClient;

        $this->cloudFrontUrl = $cloudFrontUrl;
        if ($cloudFrontPrivateKey && strpos($cloudFrontPrivateKey, '-----BEGIN ') === false) {
            $cloudFrontPrivateKey = sprintf("-----BEGIN RSA PRIVATE KEY-----\n%s\n-----END RSA PRIVATE KEY-----\n", $cloudFrontPrivateKey);
        }
        $cloudFrontPrivateKey = str_replace('\n', "\n", $cloudFrontPrivateKey);
        $this->cloudFrontPrivateKey = $cloudFrontPrivateKey;
        $this->cloudFrontKeyPairId = $cloudFrontKeyPairId;
        $this->requestSignatureTtl = $requestSignatureTtl;
    }

    /**
     * @param PublicationAsset|Asset $publicationAsset
     */
    public function generateAssetUrl(MediaInterface $media, bool $download = false): string
    {
        return $this->generateUrl($media->getPath(), $download);
    }

    /**
     * @param PublicationAsset|Asset $publicationAsset
     */
    public function generateSubDefinitionUrl(SubDefinition $subDefinition, bool $download = false): string
    {
        return $this->generateUrl($subDefinition->getPath(), $download);
    }

    private function generateUrl(string $path, bool $download): string
    {
        $options = [];
        if ($download) {
            $options['ResponseContentDisposition'] = sprintf(
                'attachment; filename=%s',
                basename($path)
            );
        }

        if ($this->cloudFrontUrl) {
            return $this->cloudFrontClient->getSignedUrl([
                'url' => $this->cloudFrontUrl.'/'.$path,
                'expires' => time() + $this->requestSignatureTtl,
                'private_key' => $this->cloudFrontPrivateKey,
                'key_pair_id' => $this->cloudFrontKeyPairId,
            ]);
        }

        return $this->requestSigner->signUri(
            $this->storageBaseUrl.'/'.$path,
            $this->requestStack->getCurrentRequest() ?? Request::create('/'),
            $options
        );
    }
}
