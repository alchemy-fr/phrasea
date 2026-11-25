<?php

declare(strict_types=1);

namespace App\Databox;

interface DataboxClientInterface
{
    /**
     * GETs a resource of the Databox API on behalf of the current user, or of the integration of the
     * target for an upload link (the values of the entity lists only), and decodes its JSON answer.
     *
     * @throws DataboxException
     */
    public function get(string $path, array $query = []): array;
}
