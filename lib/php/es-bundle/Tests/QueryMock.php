<?php

namespace Alchemy\ESBundle\Tests;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query;

class QueryMock extends Query
{
    public function __construct(EntityManagerInterface $em, private readonly array $results)
    {
        parent::__construct($em);
    }

    public function getResult($hydrationMode = self::HYDRATE_OBJECT)
    {
        return $this->results;
    }
}
