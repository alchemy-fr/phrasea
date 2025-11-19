<?php

declare(strict_types=1);

namespace App\Configurator\Vendor\RabbitMq;

use App\Configurator\ConfiguratorInterface;
use App\Service\ServiceWaiter;
use App\Util\HttpClientUtil;
use Symfony\Component\Console\Output\OutputInterface;

final readonly class RabbitMqConfigurator implements ConfiguratorInterface
{
    public const string S3_EVENTS_VHOST = 's3events';

    public function __construct(
        private RabbitMqManager $rabbitMqManager,
        private array $symfonyApplications,
        private ServiceWaiter $serviceWaiter,
    ) {
    }

    public static function getPriority(): int
    {
        return 20;
    }

    public static function getName(): string
    {
        return 'rabbitmq';
    }

    public function configure(OutputInterface $output, array $presets): void
    {
        HttpClientUtil::waitForHostPort($output, getenv('RABBITMQ_HOST'), (int) getenv('RABBITMQ_PORT'));
        $rabbitConsoleUrl = getenv('RABBITMQ_CONSOLE_URL');
        if (empty($rabbitConsoleUrl)) {
            throw new \RuntimeException('RABBITMQ_CONSOLE_URL environment variable is not set');
        }
        $this->serviceWaiter->waitForService($output, $rabbitConsoleUrl);

        foreach ($this->symfonyApplications as $symfonyApplication) {
            $vhost = getenv(sprintf('%s_RABBITMQ_VHOST', strtoupper($symfonyApplication)));
            $this->rabbitMqManager->addVhost($vhost);
            $this->rabbitMqManager->setPermissions($vhost, getenv('RABBITMQ_USER'));
            $output->writeln(sprintf('RabbitMQ vhost created for %s application: %s', $symfonyApplication, $vhost));
        }

        $s3EventVhostName = self::S3_EVENTS_VHOST;
        $this->rabbitMqManager->addVhost($s3EventVhostName);
        $this->rabbitMqManager->setPermissions($s3EventVhostName, getenv('RABBITMQ_USER'));
        $output->writeln(sprintf('RabbitMQ vhost created for S3 events: %s', $s3EventVhostName));

        $this->rabbitMqManager->createExchange(
            $s3EventVhostName,
            $s3EventVhostName,
            type: 'direct',
            durable: true
        );
        $output->writeln(sprintf('RabbitMQ exchange created for S3 events: %s', $s3EventVhostName));

        $this->rabbitMqManager->declareQueue(
            $s3EventVhostName,
            $s3EventVhostName,
            durable: true
        );
        $output->writeln(sprintf('RabbitMQ queue created for S3 events: %s', $s3EventVhostName));

        $this->rabbitMqManager->declareBinding(
            $s3EventVhostName,
            $s3EventVhostName,
            $s3EventVhostName,
        );
        $output->writeln('RabbitMQ binding created for S3 events');
    }
}
