<?php

namespace App\Configurator\Vendor\Minio;

use App\Service\ServiceWaiter;
use App\Util\EnvHelper;
use Aws\S3\S3Client;
use Symfony\Component\Console\Output\OutputInterface;

final readonly class MinioManager
{
    private const string DEFAULT_AMQP_ARN = 'arn:minio:sqs::PRIMARY:amqp';

    public function __construct(
        private S3Client $s3Client,
        private ServiceWaiter $serviceWaiter,
    ) {
    }

    public function awaitService(OutputInterface $output): void
    {
        $endpoint = EnvHelper::getEnv('S3_INTERNAL_URL') ?: EnvHelper::getEnvOrThrow('S3_ENDPOINT');
        $this->serviceWaiter->waitForService($output, $endpoint, successCodes: [200, 403]);
    }

    public function configureAmqpNotification(string $bucketName): void
    {
        $this->s3Client->putBucketNotificationConfiguration([
            'Bucket' => $bucketName,
            'NotificationConfiguration' => [
                'QueueConfigurations' => [
                    [
                        'Id' => 'phrasea-indexer',
                        'QueueArn' => EnvHelper::getEnv('MINIO_NOTIFY_AMQP_ARN') ?: self::DEFAULT_AMQP_ARN,
                        'Events' => [
                            's3:ObjectCreated:*',
                            's3:ObjectRemoved:*',
                        ],
                    ],
                ],
            ],
        ]);
    }
}
