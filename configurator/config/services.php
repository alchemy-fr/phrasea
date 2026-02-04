<?php

declare(strict_types=1);

use Alchemy\CoreBundle\Documentation\DocumentationGeneratorInterface;
use Aws\S3\S3Client;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $containerConfigurator): void {
    $parameters = $containerConfigurator->parameters();
    $parameters->set('env(MINIO_CONSOLE_URL)', 'http://localhost:9001');

    $services = $containerConfigurator->services();

    $services->defaults()
        ->autowire()
        ->autoconfigure()
        ->bind('string $keycloakRealm', '%env(KEYCLOAK_REALM_NAME)%')
        ->bind('array $symfonyApplications', [
            'databox',
            'expose',
            'uploader',
        ])
        ->bind('array $frontendApplications', [
            'databox',
            'expose',
            'uploader',
            'dashboard',
        ]);

    $services->load('App\\', __DIR__.'/../src/')
        ->exclude([
            __DIR__.'/../src/DependencyInjection/',
            __DIR__.'/../src/Entity/',
            __DIR__.'/../src/Kernel.php',
        ]);

    $services->instanceof(DocumentationGeneratorInterface::class)
        ->tag(DocumentationGeneratorInterface::TAG);

    $services->set(S3Client::class)
        ->arg(0, [
            'credentials' => [
                'key' => '%env(S3_ACCESS_KEY)%',
                'secret' => '%env(S3_SECRET_KEY)%',
            ],
            'region' => '%env(S3_REGION)%',
            'use_path_style_endpoint' => '%env(bool:S3_USE_PATH_STYLE_ENDPOINT)%',
            'endpoint' => '%env(S3_ENDPOINT)%',
            'http' => [
                'verify' => '%env(bool:VERIFY_SSL)%',
            ],
        ]);
};
