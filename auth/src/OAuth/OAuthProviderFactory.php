<?php

declare(strict_types=1);

namespace App\OAuth;

use App\OAuth\ResourceOwner\ResourceOwnerInterface;
use Http\Client\Common\HttpMethodsClient;
use HWI\Bundle\OAuthBundle\OAuth\RequestDataStorageInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Http\HttpUtils;

class OAuthProviderFactory
{
    /**
     * @var array
     */
    private $resourceOwners = [];
    /**
     * @var HttpMethodsClient
     */
    private $httpClient;
    /**
     * @var HttpUtils
     */
    private $httpUtils;
    /**
     * @var RequestDataStorageInterface
     */
    private $storage;
    /**
     * @var UrlGeneratorInterface
     */
    private $urlGenerator;
    /**
     * @var array
     */
    private $oAuthProviders;

    public function __construct(
        HttpMethodsClient $httpClient,
        HttpUtils $httpUtils,
        RequestDataStorageInterface $storage,
        UrlGeneratorInterface $urlGenerator,
        array $oAuthProviders
    ) {
        $this->httpClient = $httpClient;
        $this->httpUtils = $httpUtils;
        $this->storage = $storage;
        $this->urlGenerator = $urlGenerator;
        $this->oAuthProviders = $oAuthProviders;
    }

    public function addResourceOwner(string $key, string $resourceOwnerClass): void
    {
        $this->resourceOwners[$key] = $resourceOwnerClass;
    }

    public function getViewProviders(): array
    {
        return array_map(function (array $provider) {
            return [
                'title' => $provider['title'],
                'entrypoint' => $this->urlGenerator->generate('admin_oauth_authorize', [
                    'provider' => $provider['name'],
                ])
            ];
        }, $this->oAuthProviders);
    }

    public function createResourceOwner(string $providerName): ResourceOwnerInterface
    {
        $providerConfig = array_filter($this->oAuthProviders, function (array $node) use ($providerName) {
            return $node['name'] === $providerName;
        })[0];

        $class = $this->resourceOwners[$providerConfig['type']];
        $options = $providerConfig['options'];

        $provider = new $class(
            $this->httpClient,
            $this->httpUtils,
            $options,
            $providerConfig['name'],
            $this->storage
        );

        return $provider;
    }
}
