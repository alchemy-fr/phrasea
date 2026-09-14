<?php

declare(strict_types=1);

namespace App\Configurator\Vendor\Minio;

use App\Configurator\ConfiguratorInterface;
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
        return -200;
    }

    public function configure(OutputInterface $output, array $presets): void
    {
        $bucketName = EnvHelper::getEnv('INDEXER_BUCKET_NAME');
        if (!$bucketName) {
            $output->writeln('INDEXER_BUCKET_NAME environment variable is not set. Skipping Databox Indexer Minio bucket creation.');

            return;
        }

        $this->minioManager->awaitService($output);
        $this->minioManager->configureAmqpNotification($bucketName);
        $output->writeln('Minio AMQP notification configured.');
    }
}
