<?php

declare(strict_types=1);

namespace App\Consumer\Handler;

use Alchemy\ESBundle\Indexer\SearchIndexer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\MessageBusInterface;

abstract readonly class AbstractBatchHandler
{
    public function __construct(
        protected SearchIndexer $searchIndexer,
        protected EntityManagerInterface $em,
        protected MessageBusInterface $bus,
    ) {
    }

    protected function doHandle(): void
    {
        $iterator = $this->getIterator();
        $batchSize = $this->getBatchSize();

        $stack = [];
        $i = 0;
        foreach ($iterator as $item) {
            $stack[] = (string) $item['id'];
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
