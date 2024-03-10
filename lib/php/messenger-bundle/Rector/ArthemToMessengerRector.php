<?php

namespace Alchemy\MessengerBundle\Rector;

use Arthem\Bundle\RabbitBundle\Consumer\Event\EventMessageHandlerInterface;
use PhpParser\Node;
use PhpParser\Node\Stmt\Class_;
use Rector\Core\Configuration\RenamedClassesDataCollector;
use Rector\Core\Rector\AbstractRector;
use Rector\FamilyTree\Reflection\FamilyRelationsAnalyzer;
use Rector\Renaming\NodeManipulator\ClassRenamer;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;
use PhpParser\Node\Identifier;
use PhpParser\Node\Expr\MethodCall;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;

final class ArthemToMessengerRector extends AbstractRector
{
    public function __construct(
        private readonly FamilyRelationsAnalyzer $familyRelationsAnalyzer,
        private readonly ClassRenamer $classRenamer,
        private readonly RenamedClassesDataCollector $renamedClassesDataCollector,
    )
    {
    }

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


        if ($className === null) {
            return null;
        }

        if (!str_ends_with($className, 'Handler')) {
            return null;
        }

        if ($this->isHandler($node)) {
            $oldName = $node->namespacedName->toString();
            $newName = preg_replace('#\\\Consumer(\\\Handler)?(?=\\\)#', '\\Message', $oldName);
            $this->renamedClassesDataCollector->addOldToNewClasses([
                $oldName => $newName,
            ]);

            $this->classRenamer->renameNode($node, [$oldName => $newName], null);
        }

        // return $node if you modified it
        return $node;
    }

    private function isHandler(Class_ $class, int $depth = 0): bool
    {
        if ($depth > 20) {
            return false;
        }

        $ancestors = $this->familyRelationsAnalyzer->getClassLikeAncestorNames($class);

        if (in_array(EventMessageHandlerInterface::class, $ancestors, true)) {
            return true;
        }

        return false;
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
