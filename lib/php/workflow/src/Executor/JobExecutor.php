<?php

declare(strict_types=1);

namespace Alchemy\Workflow\Executor;

use Alchemy\Workflow\Executor\Action\ActionRegistryInterface;
use Alchemy\Workflow\Model\Job;

class JobExecutor
{
    /**
     * @var ExecutorInterface[]
     */
    private iterable $executors;
    private ActionRegistryInterface $actionRegistry;

    public function __construct(iterable $executors, ActionRegistryInterface $actionRegistry)
    {
        $this->executors = $executors;
        $this->actionRegistry = $actionRegistry;
    }

    public function executeJob(JobExecutionContext $context, Job $job): void
    {
        $output = $context->getOutput();
        $output->writeln(sprintf('Running job <info>%s</info>', $job->getId()));

        foreach ($job->getSteps() as $step) {
            $jobState = $context->getJobState();

            try {
                $executorName = $step->getExecutor();
                $output->writeln(sprintf('Running step <info>%s</info>', $step->getId()));

                $runContext = new RunContext($output, $context->getInputs(), []);

                if (!empty($step->getUses())) {
                    $action = $this->actionRegistry->getAction($step->getUses());
                    $action->handle($runContext);
                } else {
                    foreach ($this->executors as $executor) {
                        if ($executor->support($executorName)) {
                            $executor->execute($step, $runContext);

                            break;
                        }
                    }
                }
            } catch (\Throwable $e) {
                $jobState->setError(sprintf('%s [%s:%d]
%s',
                    $e->getMessage(),
                    $e->getFile(),
                    $e->getLine(),
                    $e->getTraceAsString(),
                ));
                if (!$step->isContinueOnError()) {
                    throw $e;
                }
                $output->writeln(sprintf('<error>Step <info>%s</info> has failed but is set to continue on error</error>', $step->getId()));
            } finally {
                if (isset($runContext)) {
                    $jobState->setOutputs($runContext->getOutputs());
                }
            }
        }
    }
}
