<?php

declare(strict_types=1);

namespace App\Configurator\Vendor\Minio;

use App\Configurator\ConfiguratorInterface;
use App\Configurator\Vendor\RabbitMq\RabbitMqConfigurator;
use App\Util\EnvHelper;
use Symfony\Component\Console\Output\OutputInterface;

final readonly class MinioConfigurator implements ConfiguratorInterface
{
    public function __construct(
        private MinioManager $minioManager,
    ) {
    }

    public static function getName(): string
    {
        return 'minio';
    }

    public static function getPriority(): int
    {
        return 200;
    }

    public function configure(OutputInterface $output, array $presets): void
    {
        $bucketName = EnvHelper::getEnv('INDEXER_BUCKET_NAME');
        if (!$bucketName) {
            $output->writeln('INDEXER_BUCKET_NAME environment variable is not set. Skipping Databox Indexer Minio bucket creation.');

            return;
        }

        $this->minioManager->awaitService($output);
        $output->writeln(sprintf('Minio bucket created for Databox Indexer: %s', $bucketName));
        $this->minioManager->configureAmqpNotification($output, $bucketName, RabbitMqConfigurator::S3_EVENTS_VHOST);
        $output->writeln('Minio AMQP notification configured.');
    }
}
