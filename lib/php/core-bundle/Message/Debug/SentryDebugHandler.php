<?php

namespace Alchemy\CoreBundle\Message\Debug;

use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class SentryDebugHandler
{
    public function __invoke(SentryDebug $message): void
    {
        throw new \InvalidArgumentException(sprintf('Test error in messenger handler %s', $message->getId()));
    }
}
