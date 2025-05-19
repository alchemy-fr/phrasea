<?php

declare(strict_types=1);

namespace Alchemy\Workflow\Executor;

use Alchemy\Workflow\Date\MicroDateTime;
use Alchemy\Workflow\Exception\ConcurrencyException;
use Alchemy\Workflow\Exception\JobSkipExceptionInterface;
use Alchemy\Workflow\Executor\Action\ActionRegistryInterface;
use Alchemy\Workflow\Executor\Expression\ExpressionParser;
use Alchemy\Workflow\Model\Job;
use Alchemy\Workflow\Model\Step;
use Alchemy\Workflow\State\Inputs;
use Alchemy\Workflow\State\JobState;
use Alchemy\Workflow\State\WorkflowState;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\Console\Output\OutputInterface;

final readonly class JobExecutor
{
    private LoggerInterface $logger;
    private OutputInterface $output;
    private EnvContainer $envs;

    public function __construct(
        private iterable $executors,
        private ActionRegistryInterface $actionRegistry,
        private ExpressionParser $expressionParser,
        private JobStateManager $jobStateManager,
        ?OutputInterface $output = null,
        ?LoggerInterface $logger = null,
        ?EnvContainer $envs = null,
    ) {
        $this->logger = $logger ?? new NullLogger();
        $this->output = $output ?? new NullOutput();
        $this->envs = $envs ?? new EnvContainer();
    }

    private function shouldBeSkipped(JobExecutionContext $context, Job $job): bool
    {
        if (null !== $if = $job->getIf()) {
            if (str_contains($if, '::')) {
                $if = preg_replace_callback('#(\w[\w\\\]*)::([\w]+)#', function (array $regs) use ($context): string {
                    [, $class, $method] = $regs;
                    if (class_exists($class)) {
                        $action = $this->actionRegistry->getAction($class);

                        return call_user_func([$action, $method], $context) ? 'true' : 'false';
                    }

                    return $regs[0];
                }, $if);
            }

            return !$this->expressionParser->evaluateIf($if, $context);
        }

        return false;
    }

    public function executeJob(WorkflowState $workflowState, Job $job, string $jobStateId, array $env = []): void
    {
        $context = $this->jobStateManager->wrapInTransaction(function () use ($workflowState, $job, $jobStateId, $env): ?JobExecutionContext {
            $workflowId = $workflowState->getId();

            $this->jobStateManager->acquireJobLock($workflowId, $jobStateId);

            $jobState = $this->jobStateManager->getJobState($workflowId, $jobStateId);

            $jobId = $job->getId();

            try {
                $status = $jobState->getStatus();
                if (JobState::STATUS_TRIGGERED !== $status) {
                    if (JobState::STATUS_CANCELLED === $status) {
                        return null;
                    }

                    throw new ConcurrencyException(sprintf('Job "%s" has not the "%s" status for workflow "%s" (got "%s")', $jobId, JobState::STATUS_LABELS[JobState::STATUS_TRIGGERED], $workflowId, JobState::STATUS_LABELS[$status]));
                }

                $context = new JobExecutionContext(
                    $workflowState,
                    $jobState,
                    $this->output,
                    $this->envs->mergeWith($env),
                    ($workflowState->getEvent()?->getInputs() ?? new Inputs())->mergeWith($jobState->getInputs()?->getArrayCopy() ?? [])
                );

                $jobInputs = $context->getInputs()
                    ->mergeWith($this->expressionParser->evaluateArray($job->getWith()->getArrayCopy(), $context));
                $context->replaceInputs($jobInputs);

                $jobState->setInputs($jobInputs);

                try {
                    $shouldBeSkipped = $this->shouldBeSkipped($context, $job);
                } catch (\Throwable $e) {
                    $error = sprintf('Error while evaluating if condition: %s', $e->getMessage());
                    $this->logger->error($error);
                    $jobState->addError($error);
                    $jobState->setStatus(JobState::STATUS_ERROR);
                    $this->jobStateManager->persistJobState($jobState);

                    return null;
                }

                if ($shouldBeSkipped) {
                    $jobState->setStatus(JobState::STATUS_SKIPPED);
                    $this->jobStateManager->persistJobState($jobState);

                    return null;
                }

                $jobState->setStatus(JobState::STATUS_RUNNING);
                $jobState->setStartedAt(new MicroDateTime());
                $this->jobStateManager->persistJobState($jobState);

                return $context;
            } catch (\Throwable $e) {
                try {
                    $this->jobStateManager->releaseJobLock($workflowId, $jobState->getId());
                } catch (\Throwable $e2) {
                    throw new \RuntimeException(sprintf('Error while releasing job lock after another error: %s (First error was: %s)', $e2->getMessage(), $e->getMessage()), 0, $e);
                }

                throw $e;
            }
        });

        $this->jobStateManager->flushEvents();

        if (null === $context) {
            return;
        }

        $this->runJob($context, $job);

        $this->jobStateManager->flushEvents();
    }

    private function runJob(JobExecutionContext $context, Job $job): void
    {
        $jobState = $context->getJobState();

        $jobEnvContainer = $context->getEnvs()->mergeWith($this->expressionParser->evaluateArray(
            $job->getEnv()->getArrayCopy(),
            $context
        ));

        $output = $context->getOutput();
        $output->writeln(sprintf('Running job <info>%s</info>', $job->getId()));

        $endStatus = JobState::STATUS_SUCCESS;

        foreach ($job->getSteps() as $step) {
            $stepState = $jobState->initStep($step->getId());
            $output->writeln(sprintf('Running step <info>%s</info>', $step->getId()));

            $runContext = new RunContext(
                $jobState,
                $output,
                $context->getInputs()->mergeWith($this->expressionParser->evaluateArray($step->getWith(), $context)),
                $jobEnvContainer->mergeWith($this->expressionParser->evaluateArray(
                    $step->getEnv()->getArrayCopy(),
                    $context
                )),
                $stepState->getOutputs()
            );

            $jobCallable = $this->getJobCallable($step, $context, $runContext);
            $stepState->setStartedAt(new MicroDateTime());

            try {
                $jobCallable($runContext);
            } catch (\Throwable $e) {
                $endStatus = JobState::STATUS_FAILURE;
                $jobState->addException($e);

                if ($e instanceof JobSkipExceptionInterface && $e->shouldSkipJob()) {
                    $endStatus = JobState::STATUS_SKIPPED;
                    $this->logger->info(sprintf('Skipping job <info>%s</info>: %s', $job->getId(), $e->getMessage()), [
                        'exception' => $e,
                        'step' => $step->getId(),
                        'job' => $job->getId(),
                    ]);
                } else {
                    $this->logger->error($e->getMessage(), [
                        'exception' => $e,
                        'step' => $step->getId(),
                        'job' => $job->getId(),
                    ]);
                }

                if (!$step->isContinueOnError()) {
                    break;
                }

                $output->writeln(sprintf('<error>Step <info>%s</info> has failed but is set to continue on error</error>', $step->getId()));
            } finally {
                $stepState->setEndedAt(new MicroDateTime());

                if ($runContext->isRetainJob()) {
                    $this->extractOutputs($job, $context);
                    $this->jobStateManager->persistJobState($jobState, releaseLock: false);

                    return;
                }
            }
        }

        $this->extractOutputs($job, $context);

        $jobState->setEndedAt(new MicroDateTime());
        $jobState->setStatus($endStatus);
        $this->jobStateManager->persistJobState($jobState, releaseLock: false);
    }

    private function getJobCallable(Step $step, JobExecutionContext $context, RunContext $runContext): callable
    {
        $executorName = $step->getExecutor();

        if (!empty($step->getUses())) {
            $action = $this->actionRegistry->getAction($step->getUses());

            return fn (RunContext $runContext) => $action->handle($runContext);
        } else {
            foreach ($this->executors as $executor) {
                if ($executor->support($executorName)) {
                    $run = $this->expressionParser->evaluateRun($step->getRun(), $context, $runContext);

                    return fn (RunContext $runContext) => $executor->execute($run, $runContext);
                }
            }
        }

        throw new \InvalidArgumentException('Could not find executor');
    }

    private function extractOutputs(Job $job, JobExecutionContext $context): void
    {
        $outputs = $context->getJobState()->getOutputs();
        foreach ($job->getOutputs() as $key => $value) {
            try {
                $resolved = $this->expressionParser->evaluateJobExpression($value, $context);
                $outputs->set($key, $resolved);
            } catch (\Throwable $e) {
                $this->logger->error($e->getMessage());

                throw new \RuntimeException(sprintf('Error while evaluating expression "%s": %s', $value, $e->getMessage()), 0, $e);
            }
        }
    }
}
