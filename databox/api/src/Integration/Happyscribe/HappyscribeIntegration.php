<?php

declare(strict_types=1);

namespace App\Integration\Happyscribe;

use Alchemy\Workflow\Model\Workflow;
use App\Integration\AbstractIntegration;
use App\Integration\IntegrationConfig;
use App\Integration\WorkflowHelper;
use App\Integration\WorkflowIntegrationInterface;
use Symfony\Component\Config\Definition\Builder\NodeBuilder;

class HappyscribeIntegration extends AbstractIntegration implements WorkflowIntegrationInterface
{
    final public const array ALLOWED_EXTENSIONS = ['srt', 'txt', 'json', 'vtt', 'docx', 'pdf', 'html'];

    public function getWorkflowJobDefinitions(IntegrationConfig $config, Workflow $workflow): iterable
    {
        yield WorkflowHelper::createIntegrationJob(
            $config,
            HappyscribeAction::class,
        );
    }

    public function buildConfiguration(NodeBuilder $builder): void
    {
        $builder
            ->scalarNode('organizationId')
                ->isRequired()
                ->cannotBeEmpty()
                ->info('ID of the Happyscribe organization')
            ->end()
            ->scalarNode('apiKey')
                ->isRequired()
                ->cannotBeEmpty()
                ->info('API key to access the Happyscribe API')
            ->end()
            ->enumNode('transcriptFormat')
                ->defaultValue('vtt')
                ->values(self::ALLOWED_EXTENSIONS)
                ->info('Specify the export format of the transcript (srt, vtt, txt, docx, pdf, json, html)')
            ->end()
            ->scalarNode('attribute')
                ->defaultValue('subtitle')
                ->cannotBeEmpty()
                ->info('The slug attribute to store the transcription in')
            ->end()
            ->scalarNode('sourceLanguageAttribute')
                ->defaultValue('sourceLanguage')
                ->cannotBeEmpty()
                ->info('The slug attribute to retrieve the source language for an asset')
            ->end()
            ->scalarNode('defaultSourceLanguage')
                ->defaultValue('en')
                ->cannotBeEmpty()
                ->info('Define the default source language if no attribute value "sourceLanguageAttribute" is found')
            ->end()
            ->scalarNode('rendition')
                ->info('Not providing rendition name will use the source file')
            ->end()
        ;
    }

    public static function getTitle(): string
    {
        return 'Happyscribe';
    }

    public static function getName(): string
    {
        return 'happyscribe';
    }
}
