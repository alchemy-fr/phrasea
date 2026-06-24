<?php

declare(strict_types=1);

namespace App\Elasticsearch\AQL;

enum ExpressionOperatorEnum: string
{
    case PLUS = '+';
    case MINUS = '-';
    case MULTIPLY = '*';
    case DIVIDE = '/';
}
