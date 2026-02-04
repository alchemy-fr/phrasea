<?php

namespace App\Configurator\Vendor\Minio;

use App\Service\ServiceWaiter;
use App\Util\EnvHelper;
use App\Util\HttpClientUtil;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final readonly class MinioManager
{
    public function __construct(
        private HttpClientInterface $minioClient,
        private ServiceWaiter $serviceWaiter,
    ) {
    }

    public function awaitService(OutputInterface $output): void
    {
        $this->serviceWaiter->waitForService($output, EnvHelper::getEnvOrThrow('MINIO_CONSOLE_URL'));
    }

    public function configureAmqpNotification(OutputInterface $output, string $bucketName, string $vhost): void
    {
        $token = $this->getToken();
        $amqpDsn = sprintf('amqp://%s:%s@%s:%s/%s',
            EnvHelper::getEnvOrThrow('RABBITMQ_USER'),
            EnvHelper::getEnvOrThrow('RABBITMQ_PASSWORD'),
            EnvHelper::getEnvOrThrow('RABBITMQ_HOST'),
            EnvHelper::getEnvOrThrow('RABBITMQ_PORT'),
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
                'accessKey' => EnvHelper::getEnvOrThrow('S3_ACCESS_KEY'),
                'secretKey' => EnvHelper::getEnvOrThrow('S3_SECRET_KEY'),
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
