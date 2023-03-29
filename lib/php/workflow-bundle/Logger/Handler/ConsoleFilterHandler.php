<?php

declare(strict_types=1);

namespace Alchemy\WorkflowBundle\Logger\Handler;

use Monolog\Handler\FilterHandler;
use Monolog\Handler\HandlerInterface;
use Monolog\Logger;

class ConsoleFilterHandler extends FilterHandler
{
    private bool $enabled;

    public function __construct(HandlerInterface $handler, bool $bubble = true)
    {
        parent::__construct($handler, Logger::DEBUG, Logger::EMERGENCY, $bubble);

        $this->enabled = 'cli' !== php_sapi_name();
    }

    public function isHandling(array $record): bool
    {
        return $this->enabled && parent::isHandling($record);
    }
}
