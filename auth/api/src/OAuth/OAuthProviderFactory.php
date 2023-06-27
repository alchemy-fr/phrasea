<?php

declare(strict_types=1);

namespace App\OAuth;

use Http\Client\Common\HttpMethodsClient;
use HWI\Bundle\OAuthBundle\OAuth\RequestDataStorageInterface;
use HWI\Bundle\OAuthBundle\OAuth\ResourceOwnerInterface;
use Symfony\Component\Security\Http\HttpUtils;

class OAuthProviderFactory
{
    private array $resourceOwners = [];

    public function __construct(private readonly HttpMethodsClient $httpClient, private readonly HttpUtils $httpUtils, private readonly RequestDataStorageInterface $storage, private readonly array $oAuthProviders)
    {
    }

    public function addResourceOwner(string $key, string $resourceOwnerClass): void
    {
        $this->resourceOwners[$key] = $resourceOwnerClass;
    }

    public function createResourceOwner(string $providerName): ResourceOwnerInterface
    {
        $providers = array_values(array_filter($this->oAuthProviders, fn(array $node): bool => $node['name'] === $providerName));

        if (!isset($providers[0])) {
            throw new \InvalidArgumentException(sprintf('Provider "%s" does not exist in Auth service', $providerName));
        }

        $providerConfig = $providers[0];
        $class = $this->resourceOwners[$providerConfig['options']['type']] ?? null;
        if (null === $class) {
            throw new \InvalidArgumentException(sprintf('Undefined resource owner "%s"', $providerConfig['options']['type']));
        }
        $options = $providerConfig['options'];
        unset($options['type']);

        return new $class(
            $this->httpClient,
            $this->httpUtils,
            $options,
            $providerConfig['name'],
            $this->storage
        );
    }
}
