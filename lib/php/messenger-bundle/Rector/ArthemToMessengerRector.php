<?php

namespace Alchemy\MessengerBundle\Rector;

use PhpParser\Node;
use PhpParser\Node\Stmt\Class_;
use Rector\Core\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;
use PhpParser\Node\Identifier;
use PhpParser\Node\Expr\MethodCall;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;

final class ArthemToMessengerRector extends AbstractRector
{
    /**
     * @return array<class-string<Node>>
     */
    public function getNodeTypes(): array
    {
        // what node types are we looking for?
        // pick from
        // https://github.com/rectorphp/php-parser-nodes-docs/
        return [
            Class_::class,
        ];
    }

    /**
     * @param Class_ $node
     */
    public function refactor(Node $node): ?Node
    {
        $className = $this->getName($node->name);

        dump($className);

        if ($className === null) {
            return null;
        }

        // we only care about "set*" method names
        if (! str_starts_with($className, 'set')) {
            // return null to skip it
            return null;
        }

        $newMethodCallName = preg_replace(
            '#^set#', 'change', $className
        );

        $node->name = new Identifier($newMethodCallName);

        // return $node if you modified it
        return $node;
    }

    /**
     * This method helps other to understand the rule
     * and to generate documentation.
     */
    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            'Change method calls from set* to change*.', [
                new CodeSample(
                // code before
                    '$user->setPassword("123456");',
                    // code after
                    '$user->changePassword("123456");'
                ),
            ]
        );
    }
}
