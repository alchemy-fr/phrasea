<?php

namespace Alchemy\ESBundle\Tests;

use Alchemy\ESBundle\Indexer\ESIndexableDependencyInterface;

class A implements ESIndexableDependencyInterface
{
    private static int $idInc = 1;

    private string $id;

    /**
     * @param B[] $children
     */
    public function __construct(private readonly iterable $children, bool $withProxy = false)
    {
        $this->id = 'A'.((string) self::$idInc++);
        foreach ($this->children as $child) {
            if ($withProxy) {
                $child->createCProxy($this);
            } else {
                $child->setParent($this);
            }
        }
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getChildren(): iterable
    {
        return $this->children;
    }

    public static function reset(): void
    {
        self::$idInc = 1;
    }
}
