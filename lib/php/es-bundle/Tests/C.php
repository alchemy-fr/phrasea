<?php

namespace Alchemy\ESBundle\Tests;

use Alchemy\ESBundle\Indexer\ESIndexableDependencyInterface;

class C implements ESIndexableDependencyInterface
{
    private static int $idInc = 1;

    private string $id;

    public function __construct(private readonly A $parent)
    {
        $this->id = 'C'.((string) self::$idInc++).' ('.$this->parent->getId().')';
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getParent(): A
    {
        return $this->parent;
    }

    public static function reset(): void
    {
        self::$idInc = 1;
    }
}
