<?php

declare(strict_types=1);

namespace App\Configurator\Vendor\S3;

use App\Configurator\ConfiguratorInterface;
use App\Configurator\ConfigureContext;
use App\Util\EnvHelper;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;

#[AsTaggedItem(priority: 0)]
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

    public function configure(OutputInterface $output, ConfigureContext $context): void
    {
        $staticBucketName = EnvHelper::getEnvOrThrow('CONFIGURATOR_S3_BUCKET_NAME');
        $this->assertStaticBucketIsDedicated($staticBucketName);

        $this->s3Manager->awaitService($output);

        foreach ($this->symfonyApplications as $symfonyApplication) {
            $bucketName = EnvHelper::getEnvOrThrow(sprintf('%s_S3_BUCKET_NAME', strtoupper($symfonyApplication)));
            $this->s3Manager->createBucket($bucketName);
            $output->writeln(sprintf('S3 bucket created for %s application: %s', $symfonyApplication, $bucketName));
        }

        $this->s3Manager->createBucket($staticBucketName);
        $this->s3Manager->makePathPrefixPublic($staticBucketName, '');
        $output->writeln(sprintf('S3 bucket created for Configurator: %s', $staticBucketName));

        $bucketName = EnvHelper::getEnv('INDEXER_BUCKET_NAME');
        if ($bucketName) {
            $this->s3Manager->createBucket($bucketName);
            $output->writeln(sprintf('S3 bucket created for Databox Indexer: %s', $bucketName));
        } else {
            $output->writeln('INDEXER_BUCKET_NAME environment variable is not set. Skipping Databox Indexer S3 bucket creation.');
        }
    }

    /**
     * The whole static bucket is made public: it must not hold the files of an application.
     */
    private function assertStaticBucketIsDedicated(string $staticBucketName): void
    {
        $applicationBuckets = [];
        foreach ($this->symfonyApplications as $symfonyApplication) {
            $applicationBuckets[] = sprintf('%s_S3_BUCKET_NAME', strtoupper($symfonyApplication));
        }
        $applicationBuckets[] = 'INDEXER_BUCKET_NAME';

        foreach ($applicationBuckets as $variable) {
            if ($staticBucketName === EnvHelper::getEnv($variable)) {
                throw new \RuntimeException(sprintf('CONFIGURATOR_S3_BUCKET_NAME and %s are both "%s": the bucket of the configurator is made public, give it a bucket of its own.', $variable, $staticBucketName));
            }
        }
    }
}
