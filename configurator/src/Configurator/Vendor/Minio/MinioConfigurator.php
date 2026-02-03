<?php

declare(strict_types=1);

namespace App\Configurator\Vendor\Minio;

use App\Configurator\ConfiguratorInterface;
use App\Configurator\Vendor\RabbitMq\RabbitMqConfigurator;
use Symfony\Component\Console\Output\OutputInterface;

final readonly class MinioConfigurator implements ConfiguratorInterface
{
    public function __construct(
        private MinioManager $minioManager,
        private array $symfonyApplications,
    ) {
    }

    public static function getName(): string
    {
        return 'minio';
    }

    public static function getPriority(): int
    {
        return 0;
    }

    public function configure(OutputInterface $output, array $presets): void
    {
        $this->minioManager->awaitService($output);

        foreach ($this->symfonyApplications as $symfonyApplication) {
            $bucketName = getenv(sprintf('%s_S3_BUCKET_NAME', strtoupper($symfonyApplication)));
            $this->minioManager->createBucket($bucketName);
            $output->writeln(sprintf('Minio bucket created for %s application: %s', $symfonyApplication, $bucketName));
        }

        $bucketName = getenv('CONFIGURATOR_S3_BUCKET_NAME');
        $this->minioManager->createBucket($bucketName);
        $this->minioManager->makePathPrefixPublic($bucketName, '');
        $output->writeln(sprintf('Minio bucket created for Configurator: %s', $bucketName));

        $bucketName = getenv('INDEXER_BUCKET_NAME');
        $this->minioManager->createBucket($bucketName);
        $output->writeln(sprintf('Minio bucket created for Databox Indexer: %s', $bucketName));
        $this->minioManager->configureAmqpNotification($output, $bucketName, RabbitMqConfigurator::S3_EVENTS_VHOST);
        $output->writeln('Minio AMQP notification configured.');
    }
}
