<?php

declare(strict_types=1);

namespace Alchemy\Workflow\Loader;

use Alchemy\Workflow\Exception\ModelException;
use Alchemy\Workflow\Model\Job;
use Alchemy\Workflow\Model\Step;
use Alchemy\Workflow\Model\Workflow;
use Symfony\Component\Yaml\Yaml;

class YamlLoader
{
    public function load(string $file): Workflow
    {
        $data = Yaml::parseFile($file);

        try {
            return $this->parseWorkflow($data);
        } catch (ModelException $e) {
            throw new \InvalidArgumentException(sprintf('%s in file %s', $e->getMessage(), $file), 0, $e);
        }
    }

    private function parseWorkflow(array $data): Workflow
    {
        if (empty($data['name'])) {
            throw new ModelException('Missing workflow name');
        }

        $workflow = new Workflow($data['name']);

        if (isset($data['jobs'])) {
            $jobList = $workflow->getJobs();
            foreach ($data['jobs'] as $jobId => $job) {
                $jobList->offsetSet($jobId, $this->parseJob($job, $jobId));
            }
        }

        return $workflow;
    }

    private function parseJob(array $data, string $jobId): Job
    {
        $job = new Job($jobId);
        $job->setIf($data['if'] ?? null);

        if (isset($data['steps'])) {
            foreach ($data['steps'] as $i => $step) {
                $job->getSteps()->append($this->parseStep($step, $i));
            }
        }

        if (isset($data['needs'])) {
            foreach ($data['needs'] as $need) {
                $job->getNeeds()->append($need);
            }
        }

        return $job;
    }

    private function parseStep(array $data, int $i): Step
    {
        $id = $data['id'] ?? (string) $i;

        $step = new Step($id, $data['name'] ?? $id);

        $step->setRun($data['run'] ?? null);
        $step->setIf($data['if'] ?? null);

        return $step;
    }
}
