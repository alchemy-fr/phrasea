<?php

declare(strict_types=1);

namespace App\Twig\Sandbox;

use Symfony\Component\DependencyInjection\Attribute\Exclude;
use Twig\Environment;
use Twig\Error\RuntimeError;
use Twig\Extension\AbstractExtension;
use Twig\Node\Expression\Binary\RangeBinary;
use Twig\Node\Node;
use Twig\NodeVisitor\NodeVisitorInterface;
use Twig\TwigFunction;

/**
 * Replaces the range() function and the ".." operator of the sandboxed templates: a range of
 * billions of elements would exhaust the memory of the worker rendering it.
 *
 * Only for the sandbox: the application templates keep the native range().
 */
#[Exclude]
final class BoundedRangeExtension extends AbstractExtension implements NodeVisitorInterface
{
    public const int MAX_ELEMENTS = 10000;

    public function getFunctions(): array
    {
        return [
            new TwigFunction('range', [self::class, 'range']),
        ];
    }

    public function getNodeVisitors(): array
    {
        return [$this];
    }

    /**
     * Same arguments as the PHP function.
     *
     * @throws RuntimeError
     */
    public static function range(mixed $start, mixed $end, int|float $step = 1): array
    {
        if (is_numeric($start) && is_numeric($end) && 0 != $step) {
            $count = floor(abs((float) $end - (float) $start) / abs($step)) + 1;
            if (!is_finite($count) || $count > self::MAX_ELEMENTS) {
                throw new RuntimeError(sprintf('A range may hold up to %d elements', self::MAX_ELEMENTS));
            }
        }

        return range($start, $end, $step);
    }

    public function enterNode(Node $node, Environment $env): Node
    {
        return $node;
    }

    public function leaveNode(Node $node, Environment $env): ?Node
    {
        if ($node instanceof RangeBinary && !$node instanceof BoundedRangeBinary) {
            return new BoundedRangeBinary($node->getNode('left'), $node->getNode('right'), $node->getTemplateLine());
        }

        return $node;
    }

    public function getPriority(): int
    {
        return 0;
    }
}
