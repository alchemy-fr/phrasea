<?php

declare(strict_types=1);

namespace App\Elasticsearch\Exception;

/**
 * The lock of a running populate was released (or has expired) behind its back: another
 * populate of the same index may be running, this one must stop.
 */
final class PopulateLockLostException extends \RuntimeException
{
    public function __construct(public readonly string $indexName, ?\Throwable $previous = null)
    {
        parent::__construct(\sprintf('Populate of index "%s" stopped: its lock was released or has expired', $indexName), 0, $previous);
    }
}
