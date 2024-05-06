<?php

declare(strict_types=1);

namespace App\Integration\Moderation;

use Alchemy\Workflow\Model\Job;
use Alchemy\Workflow\Model\Step;
use Alchemy\Workflow\Model\Workflow;
use App\Integration\AbstractIntegration;
use App\Integration\IntegrationConfig;
use App\Integration\WorkflowHelper;
use App\Integration\WorkflowIntegrationInterface;
use Symfony\Component\Config\Definition\Builder\NodeBuilder;
use Symfony\Component\Validator\Constraints\All;
use Symfony\Component\Validator\Constraints\Email;

class ModerationIntegration extends AbstractIntegration implements WorkflowIntegrationInterface
{
    public function __construct(
    ) {
    }

    public function buildConfiguration(NodeBuilder $builder): void
    {
        $builder
            ->arrayNode('emails')
                ->isRequired()
                ->scalarPrototype()->end()
                ->info('People to notify when new asset comes in')
            ->end()
        ;
    }

    public function validateConfiguration(IntegrationConfig $config): void
    {
        $this->validate($config, 'emails', [
            new All([
                new Email(),
            ]),
        ]);
    }

    public function getWorkflowJobDefinitions(IntegrationConfig $config, Workflow $workflow): iterable
    {
        yield WorkflowHelper::createIntegrationJob(
            $config,
            ModerationAction::class,
        );

        $job = new Job(self::getName());
        $job->setName(self::getName());

        $step = new Step('human-moderation', 'Human Moderation');
        $step->setUses(ModerationAction::class);

        $job->getSteps()->append($step);

        yield $job;
    }

    public static function getTitle(): string
    {
        return 'Moderation';
    }

    public static function getName(): string
    {
        return 'core.moderation';
    }
}
