<?php

namespace App\Configurator\Vendor\RabbitMq;

use App\Util\EnvHelper;
use App\Util\HttpClientUtil;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final readonly class RabbitMqManager
{
    public function __construct(
        private HttpClientInterface $rabbitmqClient,
    ) {
    }

    public function addVhost(string $name): void
    {
        $this->rabbitmqClient->request('PUT', 'vhosts/'.urlencode($name));
    }

    public function setPermissions(string $vhost, string $user): void
    {
        $this->rabbitmqClient->request('PUT', sprintf('permissions/%s/%s', urlencode($vhost), urlencode($user)), [
            'json' => [
                'configure' => '.*',
                'write' => '.*',
                'read' => '.*',
            ],
        ]);
    }

    public function createExchange(string $vhost, string $exchangeName, string $type, ?bool $durable): void
    {
        $this->rabbitmqClient->request('PUT', sprintf('exchanges/%s/%s', urlencode($vhost), urlencode($exchangeName)), [
            'json' => [
                'type' => $type,
                'durable' => $durable,
            ],
        ]);
    }

    public function declareQueue(string $vhost, string $queueName, ?bool $durable): void
    {
        $this->rabbitmqClient->request('PUT', sprintf('queues/%s/%s', urlencode($vhost), urlencode($queueName)), [
            'json' => [
                'auto_delete' => false,
                'durable' => $durable,
            ],
        ]);
    }

    public function declareBinding(string $vhost, string $exchangeName, string $queueName, string $routingKey = ''): void
    {
        $this->rabbitmqClient->request('POST', sprintf('bindings/%s/e/%s/q/%s', urlencode($vhost), urlencode($exchangeName), urlencode($queueName)), [
            'json' => [
                'routing_key' => $routingKey,
            ],
        ]);
    }

    public function awaitService(OutputInterface $output): void
    {
        HttpClientUtil::waitForHostPort($output, EnvHelper::getEnvOrThrow('RABBITMQ_HOST'), (int) EnvHelper::getEnvOrThrow('RABBITMQ_PORT'));
    }
}
