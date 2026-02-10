<?php

namespace App\Configurator\Vendor\S3;

use App\Service\ServiceWaiter;
use App\Util\EnvHelper;
use Aws\S3\Exception\S3Exception;
use Aws\S3\S3Client;
use Symfony\Component\Console\Output\OutputInterface;

final readonly class S3Manager
{
    public function __construct(
        private S3Client $s3Client,
        private ServiceWaiter $serviceWaiter,
    ) {
    }

    public function createBucket(string $bucketName): void
    {
        try {
            $this->s3Client->createBucket([
                'Bucket' => $bucketName,
            ]);
        } catch (S3Exception $exception) {
            if (409 === $exception->getStatusCode()) {
                // Bucket already exists, do nothing
                return;
            }

            throw $exception;
        }
    }

    public function makePathPrefixPublic(string $bucket, string $prefix): void
    {
        if (!empty($mainPathPrefix = EnvHelper::getEnv('S3_PATH_PREFIX'))) {
            $prefix = rtrim($mainPathPrefix, '/').'/'.ltrim($prefix, '/');
        }

        $policy = [
            'Version' => '2012-10-17',
            'Statement' => [
                [
                    'Sid' => 'PublicReadForPrefix',
                    'Effect' => 'Allow',
                    'Principal' => '*',
                    'Action' => 's3:GetObject',
                    'Resource' => "arn:aws:s3:::{$bucket}/{$prefix}*",
                ],
            ],
        ];

        $this->s3Client->putBucketPolicy([
            'Bucket' => $bucket,
            'Policy' => json_encode($policy),
        ]);
    }

    public function awaitService(OutputInterface $output): void
    {
        $s3Endpoint = EnvHelper::getEnv('S3_INTERNAL_URL') ?: EnvHelper::getEnvOrThrow('S3_ENDPOINT');
        $this->serviceWaiter->waitForService($output, $s3Endpoint, successCodes: [200, 403]);
    }
}
