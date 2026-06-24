<?php

declare(strict_types=1);

namespace App\Elasticsearch;

enum SearchType: int
{
    case Match = 0;
    case Keyword = 1;
    case Other = 2;
}
