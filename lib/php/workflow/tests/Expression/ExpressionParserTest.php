<?php

declare(strict_types=1);

namespace Alchemy\Workflow\Tests\Expression;

use Alchemy\Workflow\Executor\Expression\ExpressionParser;
use PHPUnit\Framework\TestCase;

class ExpressionParserTest extends TestCase
{
    public function testConditionsCallGetters(): void
    {
        $subject = new ConditionSubject();

        $this->assertTrue(new ExpressionParser()->evaluateCondition('subject.getName() == "foo" and subject.isEnabled()', ['subject' => $subject]));
    }

    public function testConditionsCannotCallOtherMethods(): void
    {
        $subject = new ConditionSubject();

        try {
            new ExpressionParser()->evaluateCondition('subject.setName("bar") === null', ['subject' => $subject]);
            $this->fail('A setter must not be callable in a condition');
        } catch (\InvalidArgumentException) {
        }

        $this->assertSame('foo', $subject->getName());
    }
}

class ConditionSubject
{
    private string $name = 'foo';

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function isEnabled(): bool
    {
        return true;
    }
}
