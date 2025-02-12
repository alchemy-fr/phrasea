<?php

declare(strict_types=1);

namespace Alchemy\ReportBundle;

use Alchemy\ReportSDK\MockReportClient;
use Alchemy\ReportSDK\ReportClient;
use Alchemy\ReportSDK\ReportClientInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

class AlchemyReportBundle extends AbstractBundle
{
    public function prependExtension(ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $builder->prependExtensionConfig('framework', [
            'http_client' => [
                'scoped_clients' => [
                    'report.client' => [
                        'base_uri' => '%env(REPORT_API_URL)%',
                        'verify_peer' => '%env(bool:VERIFY_SSL)%',
                    ],
                ],
            ],
        ]);
    }

    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $container->parameters()
            ->set('env(REPORT_API_URL)', 'http://report-api')
        ;

        $services = $container->services();
        $services
            ->defaults()
                ->autowire()
                ->autoconfigure();

        $services->set(ReportClient::class)
            ->arg('$appName', '%alchemy_core.app_name%')
            ->arg('$appId', '%alchemy_core.app_id%')
            ->arg('$client', service('report.client'))
        ;

        $isTest = 'test' === $builder->getParameter('kernel.environment');

        $services->alias(ReportClientInterface::class, $isTest ? MockReportClient::class : ReportClient::class);

        $services->set(ReportUserService::class)
            ->arg('$reportBaseUrl', '%env(REPORT_API_URL)%')
        ;
    }
}
