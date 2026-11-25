<?php

declare(strict_types=1);

namespace Alchemy\NotifierBundle\Tests\Command;

use Alchemy\NotifierBundle\Channel\ChannelType;
use Alchemy\NotifierBundle\Command\BroadcastNotificationCommand;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Attribute\Option;

final class BroadcastNotificationCommandTest extends TestCase
{
    public function testChannelOptionDescriptionListsEveryChannel(): void
    {
        self::assertSame(
            sprintf('Restrict delivery to those channels (%s)', implode(', ', ChannelType::values())),
            BroadcastNotificationCommand::CHANNEL_DESCRIPTION,
        );

        $parameter = array_values(array_filter(
            (new \ReflectionMethod(BroadcastNotificationCommand::class, '__invoke'))->getParameters(),
            static fn (\ReflectionParameter $p): bool => 'channels' === $p->getName(),
        ))[0];
        self::assertSame(
            BroadcastNotificationCommand::CHANNEL_DESCRIPTION,
            $parameter->getAttributes(Option::class)[0]->newInstance()->description,
        );
    }
}
