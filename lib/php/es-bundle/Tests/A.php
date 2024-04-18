<?php

namespace Alchemy\ESBundle\Tests;

use Alchemy\ESBundle\Indexer\ESIndexableDependencyInterface;


class A implements ESIndexableDependencyInterface
{
    static private int $idInc = 1;

    private string $id;

    /**
     * @param B[] $children
     */
    public function __construct(private readonly iterable $children)
    {
        foreach ($this->children as $child) {
            $child->setParent($this);
        }
        $this->id = 'A'.((string) self::$idInc++);
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getChildren(): iterable
    {
        return $this->children;
    }
}
