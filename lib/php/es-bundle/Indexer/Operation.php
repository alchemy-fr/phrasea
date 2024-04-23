<?php

namespace Alchemy\ESBundle\Indexer;

enum Operation: string
{
    case Insert = 'i';
    case Upsert = 'u';
    case Delete = 'd';
}
