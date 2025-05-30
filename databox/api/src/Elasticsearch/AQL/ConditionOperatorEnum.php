<?php

namespace App\Elasticsearch\AQL;

enum ConditionOperatorEnum: string
{
    case EQUALS = '=';
    case NOT_EQUALS = '!=';
    case GT = '>';
    case GTE = '>=';
    case LT = '<';
    case LTE = '<=';
    case IN = 'IN';
    case NOT_IN = 'NOT_IN';
    case EXISTS = 'EXISTS';
    case MISSING = 'MISSING';
    case BETWEEN = 'BETWEEN';
    case NOT_BETWEEN = 'NOT_BETWEEN';
    case MATCHES = 'MATCHES';
    case NOT_MATCHES = 'NOT_MATCHES';
    case CONTAINS = 'CONTAINS';
    case NOT_CONTAINS = 'NOT_CONTAINS';
    case STARTS_WITH = 'STARTS_WITH';
    case NOT_STARTS_WITH = 'NOT_STARTS_WITH';
    case WITHIN_CIRCLE = 'WITHIN_CIRCLE';
    case WITHIN_RECTANGLE = 'WITHIN_RECTANGLE';
}
