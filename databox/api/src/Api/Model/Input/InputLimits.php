<?php

declare(strict_types=1);

namespace App\Api\Model\Input;

/**
 * Bounds of the lists accepted by the API inputs.
 */
final class InputLimits
{
    /**
     * Maximum number of IDs in a single request: covers a selection of the whole Elasticsearch result window
     * (10 000 by default) and keeps a SQL "IN" clause far below the 65 535 parameters allowed by PostgreSQL.
     */
    public const int MAX_IDS = 10_000;
}
