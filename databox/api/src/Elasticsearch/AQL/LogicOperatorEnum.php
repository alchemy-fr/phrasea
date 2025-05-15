<?php

namespace App\Elasticsearch\AQL;

enum LogicOperatorEnum: string {
    case AND = 'AND';
    case OR = 'OR';
}
