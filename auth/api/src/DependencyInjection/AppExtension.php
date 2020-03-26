<?php

declare(strict_types=1);

namespace App\DependencyInjection;

use App\OAuth\OAuthProviderFactory;
use App\Saml\SamlGroupManager;
use Symfony\Component\Config\Resource\FileResource;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;

class AppExtension extends Extension implements PrependExtensionInterface
{
    private function getGlobalConfig(?ContainerBuilder $container = null): array
    {
        $jsonConfigSrc = '/configs/config.json';
        if (file_exists($jsonConfigSrc)) {
            $config = json_decode(file_get_contents($jsonConfigSrc), true);

            if (null !== $container) {
                // Add for fresh cache
                $container->addResource(new FileResource($jsonConfigSrc));
            }
        } else {
            $config = [];
        }

        return $config;
    }

    public function load(array $configs, ContainerBuilder $container)
    {
        $config = $this->getGlobalConfig($container);

        $def = new Definition(OAuthProviderFactory::class);
        $def->setAutowired(true);
        $def->setAutoconfigured(true);
        $providers = $config['auth']['identity_providers'] ?? [];
        $oauthProviders = array_filter($providers, function (array $provider) {
            return 'oauth' === $provider['type'];
        });
        $def->setArgument('$oAuthProviders', $oauthProviders);
        $container->setDefinition($def->getClass(), $def);

        $samlProviders = array_filter($providers, function (array $provider) {
            return 'saml' === $provider['type'];
        });
        $this->loadSamlProviders($container, $samlProviders);

        if (isset($config['admin']['logo']['src'])) {
            $siteName = sprintf(
                '<img src="%s" width="%s" alt="Admin" />',
                $config['admin']['logo']['src'],
                $config['admin']['logo']['with']
            );
        } else {
            $siteName = 'Auth Admin';
        }

        $container->setParameter('easy_admin.site_name', $siteName);
    }

    private function loadSamlProviders(ContainerBuilder $container, array $samlProviders): void
    {
        $groupAttributesNames = [];
        foreach ($samlProviders as $idpName => $config) {
            if (isset($config['options']['groups_attribute'])) {
                $groupAttributesNames[$config['name']] = $config['options']['groups_attribute'];
            }
        }

        $def = new Definition(SamlGroupManager::class);
        $def->setAutowired(true);
        $def->setAutoconfigured(true);
        $def->setArgument('$groupAttributesName', $groupAttributesNames);

        $container->setDefinition(SamlGroupManager::class, $def);
    }

    public function prepend(ContainerBuilder $container)
    {
        $config = $this->getGlobalConfig();
        $providers = $config['auth']['identity_providers'] ?? [];
        $idps = [];
        foreach ($providers as $provider) {
            if ('saml' === $provider['type']) {
                $options = $provider['options'];

                $idp = [
                    'entityId' => $options['entity_id'],
                    'singleSignOnService' => [
                        'url' => $options['sso_url'],
                        'binding' => $options['sso_binding'] ?? 'urn:oasis:names:tc:SAML:2.0:bindings:HTTP-Redirect',
                    ],
                    'x509cert' => $options['x509cert'],
                ];

                if (isset($options['logout_url'])) {
                    $idp['singleLogoutService'] = [
                        'url' => $options['logout_url'],
                        'binding' => $options['logout_binding'] ?? 'urn:oasis:names:tc:SAML:2.0:bindings:HTTP-Redirect',
                    ];
                }

                $idps[$provider['name']] = $idp;
            }
        }

        if (!empty($idps)) {
            $samlConfig = [
                'idps' => $idps,
                'sp' => [
                    'entityId' => '%env(AUTH_BASE_URL)%/saml/metadata/{idp}',
                    'assertionConsumerService' => [
                        'url' => '%env(AUTH_BASE_URL)%/saml/acs',
                        'binding' => 'urn:oasis:names:tc:SAML:2.0:bindings:HTTP-POST',
                    ],
                    'singleLogoutService' => [
                        'url' => '%env(AUTH_BASE_URL)%/saml/logout',
                        'binding' => 'urn:oasis:names:tc:SAML:2.0:bindings:HTTP-Redirect',
                    ],
                    'privateKey' => '',
                ],
                'baseurl' => '%env(AUTH_BASE_URL)%/saml',
                'strict' => true,
                'contactPerson' => array_map(function (array $contact): array {
                    return [
                        'givenName' => $contact['name'],
                        'emailAddress' => $contact['email'],
                    ];
                }, $options['contacts'] ?? []),
                'organization' => $options['organization'] ?? [],
            ];

            $container->prependExtensionConfig('hslavich_onelogin_saml', $samlConfig);
        }
        $container->setParameter('has_saml_provider', !empty($idps));
    }
}
