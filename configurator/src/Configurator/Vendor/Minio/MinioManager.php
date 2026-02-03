<?php

namespace App\Configurator\Vendor\Minio;

use App\Service\ServiceWaiter;
use App\Util\HttpClientUtil;
use Aws\S3\Exception\S3Exception;
use Aws\S3\S3Client;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final readonly class MinioManager
{
    public function __construct(
        private S3Client $s3Client,
        private HttpClientInterface $minioClient,
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
        if (!empty($mainPathPrefix = getenv('S3_PATH_PREFIX'))) {
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
        $s3Endpoint = getenv('S3_ENDPOINT');
        if (empty($s3Endpoint)) {
            throw new \RuntimeException('S3_ENDPOINT environment variable is not set.');
        }
        $this->serviceWaiter->waitForService($output, $s3Endpoint, successCodes: [200, 403]);
        $this->serviceWaiter->waitForService($output, getenv('MINIO_CONSOLE_URL'));
    }

    public function configureAmqpNotification(OutputInterface $output, string $bucketName, string $vhost): void
    {
        $token = $this->getToken();
        $amqpDsn = sprintf('amqp://%s:%s@%s:%s/%s',
            getenv('RABBITMQ_USER'),
            getenv('RABBITMQ_PASSWORD'),
            getenv('RABBITMQ_HOST'),
            getenv('RABBITMQ_PORT'),
            $vhost,
        );

        $response = HttpClientUtil::debugError(function () use ($token, $amqpDsn, $vhost): array {
            return $this->minioClient->request('PUT', 'configs/notify_amqp', [
                'headers' => [
                    'Authorization' => 'Bearer '.$token,
                ],
                'json' => [
                    'key_values' => [
                        ['key' => 'url', 'value' => $amqpDsn],
                        ['key' => 'exchange', 'value' => $vhost],
                        ['key' => 'exchange_type', 'value' => 'direct'],
                        ['key' => 'mandatory', 'value' => 'false'],
                        ['key' => 'durable', 'value' => 'true'],
                        ['key' => 'no_wait', 'value' => 'false'],
                        ['key' => 'internal', 'value' => 'false'],
                        ['key' => 'auto_deleted', 'value' => 'false'],
                    ],
                ],
            ])->toArray();
        });

        if ($response['restart'] ?? false) {
            try {
                $this->minioClient->request('POST', 'service/restart', [
                    'headers' => [
                        'Authorization' => 'Bearer '.$token,
                    ],
                ]);
            } catch (TransportExceptionInterface|ServerExceptionInterface) {
                // Ignore 502
            }
        }

        $this->awaitService($output);

        HttpClientUtil::debugError(function () use ($token, $bucketName): void {
            $this->minioClient->request('POST', sprintf('buckets/%s/events', urlencode($bucketName)), [
                'headers' => [
                    'Authorization' => 'Bearer '.$token,
                ],
                'json' => [
                    'ignoreExisting' => true,
                    'configuration' => [
                        'arn' => 'arn:minio:sqs::_:amqp',
                        'events' => [
                            'put',
                            'delete',
                        ],
                    ],
                ],
            ]);
        });
    }

    private function getToken(): string
    {
        $response = $this->minioClient->request('POST', 'login', [
            'json' => [
                'accessKey' => getenv('S3_ACCESS_KEY'),
                'secretKey' => getenv('S3_SECRET_KEY'),
            ],
        ]);

        foreach ($response->getHeaders()['set-cookie'] as $cookie) {
            if (str_starts_with($cookie, 'token=')) {
                return explode(';', substr($cookie, 6))[0];
            }
        }

        throw new \RuntimeException('Unable to retrieve Minio token from login response.');
    }
}
