<?php

declare(strict_types=1);

namespace App\OAuth;

use Http\Client\Common\HttpMethodsClient;
use HWI\Bundle\OAuthBundle\OAuth\RequestDataStorageInterface;
use HWI\Bundle\OAuthBundle\OAuth\ResourceOwnerInterface;
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

    public function __construct(
        HttpMethodsClient $httpClient,
        HttpUtils $httpUtils,
        RequestDataStorageInterface $storage,
        array $resourceOwners
    ) {
        $this->httpClient = $httpClient;
        $this->httpUtils = $httpUtils;
        $this->storage = $storage;
        $this->resourceOwners = $resourceOwners;
    }

    public function createResourceOwner(string $providerName): ResourceOwnerInterface
    {
        $config = json_decode(file_get_contents('/configs/config.json'), true);
        $providerConfig = array_filter($config['auth']['oauth_providers'], function (array $node) use ($providerName) {
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
