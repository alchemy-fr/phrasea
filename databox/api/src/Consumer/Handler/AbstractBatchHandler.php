<?php

declare(strict_types=1);

namespace App\Consumer\Handler;

use Arthem\Bundle\RabbitBundle\Consumer\Event\AbstractEntityManagerHandler;
use Arthem\Bundle\RabbitBundle\Consumer\Event\EventMessage;

abstract class AbstractBatchHandler extends AbstractEntityManagerHandler
{
    public function handle(EventMessage $message): void
    {
        $iterator = $this->getIterator();
        $batchSize = $this->getBatchSize();

        $stack = [];
        $i = 0;
        foreach ($iterator as $asset) {
            $stack[] = $asset[0]['id'];
            if ($i++ > $batchSize) {
                $this->flushIndexStack($stack);
                $stack = [];
                $i = 0;
            }
        }

        if (!empty($stack)) {
            $this->flushIndexStack($stack);
        }
    }

    abstract protected function getIterator(): iterable;
    abstract protected function flushIndexStack(array $stack): void;

    protected function getBatchSize(): int
    {
        return 200;
    }
}
