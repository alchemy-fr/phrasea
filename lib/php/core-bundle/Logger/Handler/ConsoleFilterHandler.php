<?php

declare(strict_types=1);

namespace Alchemy\CoreBundle\Logger\Handler;

use Monolog\Handler\FilterHandler;
use Monolog\Handler\HandlerInterface;
use Monolog\Level;
use Monolog\LogRecord;

/**
 * A FilterHandler that mutes itself under the CLI SAPI, where the "console"
 * handler already prints the same records.
 */
class ConsoleFilterHandler extends FilterHandler
{
    private readonly bool $enabled;

    public function __construct(
        \Closure|HandlerInterface $handler,
        int|string|Level|array $minLevelOrList = Level::Debug,
        int|string|Level $maxLevel = Level::Emergency,
        bool $bubble = true,
    ) {
        parent::__construct($handler, $minLevelOrList, $maxLevel, $bubble);

        $this->enabled = 'cli' !== php_sapi_name();
    }

    public function isHandling(LogRecord $record): bool
    {
        return $this->enabled && parent::isHandling($record);
    }
}
