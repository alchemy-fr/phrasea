<?php

declare(strict_types=1);

namespace App\Elasticsearch\Exception;

use App\Elasticsearch\PopulateLockManager;

/**
 * The populate holding the lock gave a sign of life recently: releasing its lock would
 * let another populate run alongside it.
 */
final class PopulateOwnerAliveException extends \RuntimeException
{
    /**
     * @param array<string, mixed> $owner as returned by PopulateLockManager::getOwner()
     */
    public function __construct(public readonly string $indexName, public readonly array $owner)
    {
        parent::__construct(\sprintf(
            'The populate of index "%s" is still alive (%s). Release its lock anyway only if you are sure its process is dead.',
            $indexName,
            PopulateLockManager::describeOwner($owner),
        ));
    }
}
