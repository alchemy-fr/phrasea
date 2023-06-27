<?php

declare(strict_types=1);

namespace Alchemy\Workflow\Executor\Expression;

use Alchemy\Workflow\Executor\JobExecutionContext;
use Alchemy\Workflow\Executor\RunContext;
use Alchemy\Workflow\State\Inputs;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;

class ExpressionParser extends ExpressionLanguage
{
    private const DYNAMIC_PATTERN = '#\${{ +(.+?) +}}#';

    public function evaluateJobExpression(
        string $expression,
        JobExecutionContext $context,
        RunContext $runContext = null
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

    protected function registerFunctions()
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
        array $variables
    ): mixed {
        if (!is_string($expression)) {
            return $expression;
        }

        $count = preg_match_all(self::DYNAMIC_PATTERN, $expression, $matches);

        if (0 === $count) {
            return $expression;
        }

        if (1 === $count) {
            return $this->evaluate($matches[1][0], $variables);
        }

        return $this->replaceVars($expression, $variables);
    }

    public function evaluateIf(string $expression, JobExecutionContext $context, array $params = []): bool
    {
        return (bool) $this->evaluate($expression, array_merge($this->createJobVariables($context), $params));
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
        RunContext $runContext = null
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
