<?php

declare(strict_types=1);

namespace App\Elasticsearch\Exception;

/**
 * Stops a populate whose memory, once a page is released, gets close to the limit: the next
 * pages would most likely kill the process, which would leave neither its error nor its stats.
 */
final class PopulateMemoryExceededException extends \RuntimeException
{
    public function __construct(
        public readonly string $indexName,
        public readonly int $page,
        public readonly int $memory,
        public readonly int $memoryLimit,
    ) {
        parent::__construct(\sprintf(
            'Memory after page %d of index "%s": %d MB of %d MB (%d%%). Populate stopped before running out of memory, retry with a smaller --max-per-page.',
            $page,
            $indexName,
            intdiv($memory, 1024 * 1024),
            intdiv($memoryLimit, 1024 * 1024),
            (int) round($memory / $memoryLimit * 100),
        ));
    }
}
