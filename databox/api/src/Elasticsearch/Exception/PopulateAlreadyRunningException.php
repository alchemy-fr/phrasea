<?php

declare(strict_types=1);

namespace App\Elasticsearch\Exception;

final class PopulateAlreadyRunningException extends \RuntimeException
{
    public function __construct(public readonly string $indexName)
    {
        parent::__construct(\sprintf('A populate is already running for index "%s". Wait for it to finish, or delete its populate pass if the worker died.', $indexName));
    }
}
