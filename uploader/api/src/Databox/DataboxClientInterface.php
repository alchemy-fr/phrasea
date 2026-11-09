<?php

declare(strict_types=1);

namespace App\Databox;

interface DataboxClientInterface
{
    /**
     * GETs a resource of the Databox API on behalf of the current user and decodes its JSON-LD answer.
     *
     * @throws DataboxException
     */
    public function get(string $path, array $query = []): array;
}
