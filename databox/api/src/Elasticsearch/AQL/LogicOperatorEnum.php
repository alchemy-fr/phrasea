<?php

declare(strict_types=1);

namespace App\Elasticsearch\AQL;

enum LogicOperatorEnum: string
{
    case AND = 'AND';
    case OR = 'OR';
}
