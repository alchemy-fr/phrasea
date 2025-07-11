<?php

declare(strict_types=1);

namespace App\Integration\Aws\Translate;

use Alchemy\Workflow\Model\Workflow;
use App\Integration\Aws\AbstractAwsIntegration;
use App\Integration\IntegrationConfig;
use App\Integration\WorkflowHelper;
use App\Integration\WorkflowIntegrationInterface;
use Symfony\Component\Config\Definition\Builder\NodeBuilder;

class AwsTranslateIntegration extends AbstractAwsIntegration implements WorkflowIntegrationInterface
{
    public function getWorkflowJobDefinitions(IntegrationConfig $config, Workflow $workflow): iterable
    {
        yield WorkflowHelper::createIntegrationJob(
            $config,
            TranslateAction::class,
        );
    }

    public function buildConfiguration(NodeBuilder $builder): void
    {
        $this->addCredentialConfigNode($builder);
        $this->addRegionConfigNode($builder);

        $builder
            ->scalarNode('defaultSourceLanguage')
                ->isRequired()
                ->cannotBeEmpty()
                ->info('The language for the attribute no language (NO_LOCALE)')
            ->end()
            ->arrayNode('preferredSourceLanguages')
                ->isRequired()
                ->info('The language codes of the preferred source languages to translate from')
                ->scalarPrototype()
                ->end()
            ->end()
            ->arrayNode('translatedLanguages')
                ->isRequired()
                ->info('The language codes of the destination to translate to')
                ->scalarPrototype()
                ->end()
            ->end()
        ;

        $builder->append($this->createBudgetLimitConfigNode(true));
    }

    protected function getSupportedRegions(): array
    {
        return [
            'ap-northeast-1',
            'ap-northeast-2',
            'ap-south-1',
            'ap-southeast-1',
            'ap-southeast-2',
            'eu-central-1',
            'eu-west-1',
            'eu-west-2',
            'us-east-1',
            'us-east-2',
            'us-west-2',
        ];
    }

    public static function getTitle(): string
    {
        return 'AWS Translate';
    }

    public static function getName(): string
    {
        return 'aws.translate';
    }
}
