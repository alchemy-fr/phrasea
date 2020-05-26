<?php

declare(strict_types=1);

namespace Alchemy\CoreBundle\Health\Checker;

use Alchemy\CoreBundle\Health\HealthCheckerInterface;
use OldSound\RabbitMqBundle\RabbitMq\BaseAmqp;
use OldSound\RabbitMqBundle\RabbitMq\DynamicConsumer;
use Symfony\Component\DependencyInjection\ContainerInterface;

class RabbitMQConnectionChecker implements HealthCheckerInterface
{
    private ContainerInterface $container;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    public function getName(): string
    {
        return 'rabbitmq';
    }

    public function check(): bool
    {
        $amqpContainer = $this->container->get('old_sound_rabbit_mq.parts_holder');
        foreach (array('base_amqp', 'binding') as $key) {
            /** @var BaseAmqp $baseAmqp */
            foreach ($amqpContainer->getParts('old_sound_rabbit_mq.' . $key) as $baseAmqp) {
                if ($baseAmqp instanceof DynamicConsumer) {
                    continue;
                }
                $baseAmqp->getChannel();
            }
        }

        return true;
    }

    public function getAdditionalInfo(): ?array
    {
        return null;
    }
}
