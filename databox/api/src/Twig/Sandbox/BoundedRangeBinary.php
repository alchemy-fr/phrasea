<?php

declare(strict_types=1);

namespace App\Twig\Sandbox;

use Twig\Compiler;
use Twig\Node\Expression\Binary\RangeBinary;

/**
 * The ".." operator through BoundedRangeExtension::range(). Still a RangeBinary: the sandbox
 * checks it as a call to range().
 */
final class BoundedRangeBinary extends RangeBinary
{
    public function compile(Compiler $compiler): void
    {
        $compiler
            ->raw('\\'.BoundedRangeExtension::class.'::range(')
            ->subcompile($this->getNode('left'))
            ->raw(', ')
            ->subcompile($this->getNode('right'))
            ->raw(')')
        ;
    }
}
