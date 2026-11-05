<?php

declare(strict_types=1);

namespace Alchemy\Workflow\Executor\Expression;

use Alchemy\Workflow\Executor\JobExecutionContext;
use Alchemy\Workflow\Executor\RunContext;
use Alchemy\Workflow\State\Inputs;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;
use Symfony\Component\ExpressionLanguage\Node\GetAttrNode;
use Symfony\Component\ExpressionLanguage\Node\Node;

class ExpressionParser extends ExpressionLanguage
{
    private const string DYNAMIC_PATTERN = '#\${{ +(.+?) +}}#';

    public function evaluateJobExpression(
        string $expression,
        JobExecutionContext $context,
        ?RunContext $runContext = null,
    ): mixed {
        $count = preg_match_all(self::DYNAMIC_PATTERN, $expression, $matches);

        if (0 === $count) {
            return $expression;
        }

        $variables = $this->createJobVariables($context, $runContext);
        if (1 === $count) {
            return $this->evaluate($matches[1][0], $variables);
        }

        return $this->replaceVars($expression, $variables);
    }

    protected function registerFunctions(): void
    {
        parent::registerFunctions();

        $this->register('date', fn ($date) => sprintf('(new \DateTime(%s))', $date), fn (array $values, $date) => new \DateTime($date));

        $this->register('date_modify', fn ($date, $modify) => sprintf('%s->modify(%s)', $date, $modify), function (array $values, $date, $modify) {
            if (!$date instanceof \DateTime) {
                throw new \RuntimeException('date_modify() expects parameter 1 to be a Date');
            }

            return $date->modify($modify);
        });
    }

    private function evaluateDynamicExpression(
        mixed $expression,
        array $variables,
    ): mixed {
        if (!is_string($expression)) {
            return $expression;
        }

        $count = preg_match_all(self::DYNAMIC_PATTERN, $expression, $matches);

        if (0 === $count) {
            return $expression;
        }

        if (1 === $count) {
            $var = $this->evaluate($matches[1][0], $variables);
            if ($var instanceof ObjectOrArrayAccessor) {
                return $var->unwrap();
            }

            return $var;
        }

        return $this->replaceVars($expression, $variables);
    }

    public function evaluateIf(string $expression, JobExecutionContext $context, array $params = []): bool
    {
        return $this->evaluateCondition($expression, array_merge($this->createJobVariables($context), $params));
    }

    /**
     * Conditions may be written by users and get entities as variables: only getters
     * can be called on them, so that evaluating a condition never changes anything.
     */
    public function evaluateCondition(string $expression, array $variables): bool
    {
        $parsed = $this->parse($expression, array_keys($variables));
        self::assertNoMutatingCall($parsed->getNodes());

        return (bool) $this->evaluate($parsed, $variables);
    }

    private static function assertNoMutatingCall(Node $node): void
    {
        if ($node instanceof GetAttrNode && GetAttrNode::METHOD_CALL === $node->attributes['type']) {
            $method = (string) $node->nodes['attribute']->attributes['value'];
            if (1 !== preg_match('#^(get|is|has)[A-Z]#', $method)) {
                throw new \InvalidArgumentException(sprintf('Method "%s()" cannot be called in a condition', $method));
            }
        }

        foreach ($node->nodes as $child) {
            if ($child instanceof Node) {
                self::assertNoMutatingCall($child);
            }
        }
    }

    public function evaluateArray(array $array, JobExecutionContext $context): array
    {
        $variables = $this->createJobVariables($context);

        return array_map(fn ($value) => $this->evaluateDynamicExpression($value, $variables), $array);
    }

    private function replaceVars(string $literal, array $variables): string
    {
        return preg_replace_callback(
            self::DYNAMIC_PATTERN,
            fn (array $matches): string => (string) $this->evaluate($matches[1], $variables),
            $literal
        );
    }

    public function evaluateRun(string $run, JobExecutionContext $context, RunContext $runContext): string
    {
        $variables = $this->createJobVariables($context, $runContext);

        return $this->replaceVars($run, $variables);
    }

    private function createJobVariables(
        JobExecutionContext $context,
        ?RunContext $runContext = null,
    ): array {
        $workflowState = $context->getWorkflowState();
        $jobState = $context->getJobState();
        $inputs = $runContext?->getInputs() ?? $workflowState->getEvent()?->getInputs() ?? new Inputs();
        $envs = $runContext?->getEnvs() ?? $context->getEnvs();

        return [
            'steps' => new ObjectOrArrayAccessor($jobState->getSteps()),
            'jobs' => new JobsAccessor($workflowState),
            'inputs' => new ObjectOrArrayAccessor($inputs),
            'env' => new ObjectOrArrayAccessor($envs),
        ];
    }
}
