<?php

declare(strict_types=1);

namespace App\Integration\Moderation;

use Alchemy\Workflow\Model\Job;
use Alchemy\Workflow\Model\Step;
use App\Integration\AbstractIntegration;
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

    public function validateConfiguration(array $config): void
    {
        $this->validate($config, 'emails', [
            new All([
                new Email(),
            ]),
        ]);
    }

    public function getWorkflowJobDefinitions(array $config): iterable
    {
        yield WorkflowHelper::createIntegrationJob(
            self::getName(),
            self::getTitle(),
            $config,
            ModerationAction::class,
        );

        $job = new Job(self::getName());

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
