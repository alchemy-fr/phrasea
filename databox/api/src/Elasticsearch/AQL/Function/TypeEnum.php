<?php

declare(strict_types=1);

namespace App\Elasticsearch\AQL\Function;

enum TypeEnum
{
    case STRING;
    case NUMBER;
    case BOOL;
    case DATE;
}
