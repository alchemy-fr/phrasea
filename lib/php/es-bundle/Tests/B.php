<?php

namespace Alchemy\ESBundle\Tests;

use Alchemy\ESBundle\Indexer\ESIndexableDependencyInterface;

class B implements ESIndexableDependencyInterface
{
    static private int $idInc = 1;

    private string $id;
    private A $parent;

    public function __construct()
    {
        $this->id = 'B'.((string) self::$idInc++);
    }


    public function getId(): string
    {
        return $this->id;
    }

    public function getParent(): A
    {
        return $this->parent;
    }

    public function setParent(A $parent): void
    {
        $this->parent = $parent;
    }
}
