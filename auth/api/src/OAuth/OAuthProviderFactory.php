<?php

declare(strict_types=1);

namespace App\OAuth;

use Http\Client\Common\HttpMethodsClient;
use HWI\Bundle\OAuthBundle\OAuth\RequestDataStorageInterface;
use HWI\Bundle\OAuthBundle\OAuth\ResourceOwnerInterface;
use InvalidArgumentException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Http\HttpUtils;

class OAuthProviderFactory
{
    private array $resourceOwners = [];
    private HttpMethodsClient $httpClient;
    private HttpUtils $httpUtils;
    private RequestDataStorageInterface $storage;
    private UrlGeneratorInterface $urlGenerator;
    private array $oAuthProviders;

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

    public function getViewProviders(string $routePrefix = null): array
    {
        return array_map(function (array $provider) use ($routePrefix) {
            return [
                'title' => $provider['title'],
                'entrypoint' => $this->urlGenerator->generate($routePrefix.'oauth_authorize', [
                    'provider' => $provider['name'],
                ]),
            ];
        }, $this->oAuthProviders);
    }

    public function createResourceOwner(string $providerName): ResourceOwnerInterface
    {
        $providers = array_values(array_filter($this->oAuthProviders, function (array $node) use ($providerName) {
            return $node['name'] === $providerName;
        }));

        if (!isset($providers[0])) {
            throw new InvalidArgumentException(sprintf('Provider "%s" does not exist in Auth service', $providerName));
        }

        $providerConfig = $providers[0];
        $class = $this->resourceOwners[$providerConfig['options']['type']] ?? null;
        if (null === $class) {
            throw new InvalidArgumentException(sprintf('Undefined resource owner "%s"', $providerConfig['options']['type']));
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
