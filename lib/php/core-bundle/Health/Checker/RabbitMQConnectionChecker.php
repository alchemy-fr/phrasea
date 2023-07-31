<?php

declare(strict_types=1);

namespace Alchemy\CoreBundle\Health\Checker;

use Alchemy\CoreBundle\Health\HealthCheckerInterface;
use OldSound\RabbitMqBundle\RabbitMq\AmqpPartsHolder;
use OldSound\RabbitMqBundle\RabbitMq\BaseAmqp;
use OldSound\RabbitMqBundle\RabbitMq\DynamicConsumer;

final readonly class RabbitMQConnectionChecker implements HealthCheckerInterface
{
    public function __construct(private AmqpPartsHolder $amqpPartsHolder)
    {
    }

    public function getName(): string
    {
        return 'rabbitmq';
    }

    public function check(): bool
    {
        foreach (['base_amqp', 'binding'] as $key) {
            /** @var BaseAmqp $baseAmqp */
            foreach ($this->amqpPartsHolder->getParts('old_sound_rabbit_mq.'.$key) as $baseAmqp) {
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
