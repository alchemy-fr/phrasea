<?php

namespace Alchemy\ESBundle\Tests;

use Alchemy\ESBundle\Indexer\ESIndexableDependencyInterface;

class B implements ESIndexableDependencyInterface
{
    private static int $idInc = 1;

    private string $id;
    private ?A $parent = null;
    private ?C $next = null;

    public function __construct()
    {
        $this->id = 'B'.((string) self::$idInc++);
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getParent(): ?A
    {
        return $this->parent;
    }

    public function setParent(A $parent): void
    {
        $this->id .= ' ('.$parent->getId().')';
        $this->parent = $parent;
    }

    public function createCProxy(A $parent): void
    {
        $this->next = new C($parent);
    }

    public function getNext(): ?C
    {
        return $this->next;
    }

    public static function reset(): void
    {
        self::$idInc = 1;
    }
}
