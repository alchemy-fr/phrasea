<?php

declare(strict_types=1);

namespace App\Configurator\Vendor\S3;

use App\Configurator\ConfiguratorInterface;
use App\Util\EnvHelper;
use Symfony\Component\Console\Output\OutputInterface;

final readonly class S3Configurator implements ConfiguratorInterface
{
    public function __construct(
        private S3Manager $s3Manager,
        private array $symfonyApplications,
    ) {
    }

    public static function getName(): string
    {
        return 's3';
    }

    public static function getPriority(): int
    {
        return 0;
    }

    public function configure(OutputInterface $output, array $presets): void
    {
        $this->s3Manager->awaitService($output);

        foreach ($this->symfonyApplications as $symfonyApplication) {
            $bucketName = EnvHelper::getEnvOrThrow(sprintf('%s_S3_BUCKET_NAME', strtoupper($symfonyApplication)));
            $this->s3Manager->createBucket($bucketName);
            $output->writeln(sprintf('S3 bucket created for %s application: %s', $symfonyApplication, $bucketName));
        }

        $bucketName = EnvHelper::getEnvOrThrow('CONFIGURATOR_S3_BUCKET_NAME');
        $this->s3Manager->createBucket($bucketName);
        $this->s3Manager->makePathPrefixPublic($bucketName, '');
        $output->writeln(sprintf('S3 bucket created for Configurator: %s', $bucketName));

        $bucketName = EnvHelper::getEnv('INDEXER_BUCKET_NAME');
        if ($bucketName) {
            $this->s3Manager->createBucket($bucketName);
            $output->writeln(sprintf('S3 bucket created for Databox Indexer: %s', $bucketName));
        } else {
            $output->writeln('INDEXER_BUCKET_NAME environment variable is not set. Skipping Databox Indexer S3 bucket creation.');
        }
    }
}
