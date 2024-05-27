<?php

declare(strict_types=1);

namespace App\Integration\Phrasea\Expose;

use App\Entity\Basket\Basket;
use App\Integration\AbstractIntegration;
use App\Integration\BasketActionsIntegrationInterface;
use App\Integration\IntegrationConfig;
use Symfony\Component\Config\Definition\Builder\NodeBuilder;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Validator\Constraints\Url;

class ExposeIntegration extends AbstractIntegration implements BasketActionsIntegrationInterface
{
    public function __construct(private readonly UrlGeneratorInterface $urlGenerator)
    {
    }

    public function buildConfiguration(NodeBuilder $builder): void
    {
        $builder
            ->scalarNode('baseUrl')
                ->defaultValue('${EXPOSE_API_URL}')
                ->cannotBeEmpty()
                ->info('The Expose API base URL')
            ->end()
            ->scalarNode('clientId')
                ->isRequired()
                ->cannotBeEmpty()
            ->end()
            ->scalarNode('clientSecret')
            ->end()
        ;
    }

    public function handleBasketAction(
        string $action,
        Request $request,
        Basket $basket,
        IntegrationConfig $config
    ): ?Response {
        switch ($action) {
            case 'sync':

                break;
        }
    }

    public static function requiresWorkspace(): bool
    {
        return false;
    }

    public function validateConfiguration(IntegrationConfig $config): void
    {
        $this->validate($config, 'baseUrl', [
            new Url(),
        ]);
    }

    public function getConfigurationInfo(IntegrationConfig $config): array
    {
        return [
            'Redirect URI' => $this->urlGenerator->generate('integration_auth_code', [
                'integrationId' => $config->getIntegrationId(),
            ], UrlGeneratorInterface::ABSOLUTE_URL),
        ];
    }

    public static function getTitle(): string
    {
        return 'Expose';
    }

    public static function getName(): string
    {
        return 'phrasea.expose';
    }
}
