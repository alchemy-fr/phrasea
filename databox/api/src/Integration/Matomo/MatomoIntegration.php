<?php

declare(strict_types=1);

namespace App\Integration\Matomo;

use Alchemy\Workflow\Model\Workflow;
use App\Integration\AbstractIntegration;
use App\Integration\IntegrationConfig;
use App\Integration\IntegrationContext;
use App\Integration\UserActionsIntegrationInterface;
use App\Integration\WorkflowHelper;
use App\Integration\WorkflowIntegrationInterface;
use Symfony\Component\Config\Definition\Builder\NodeBuilder;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class MatomoIntegration extends AbstractIntegration implements WorkflowIntegrationInterface, UserActionsIntegrationInterface
{
    private const string ACTION_PROCESS = 'process';

    public function __construct(
        private readonly MatomoProcessor $matomoProcessor,
    ) {
    }

    public function getWorkflowJobDefinitions(IntegrationConfig $config, Workflow $workflow): iterable
    {
        yield WorkflowHelper::createIntegrationJob(
            $config,
            MatomoAction::class,
        );
    }

    public function buildConfiguration(NodeBuilder $builder): void
    {
        $builder
            ->scalarNode('matomoUrl')
                ->defaultValue('${MATOMO_URL}')
                ->isRequired()
                ->cannotBeEmpty()
            ->end()
            ->scalarNode('matomoAuthToken')
                ->defaultValue('${MATOMO_AUTH_TOKEN}')
                ->isRequired()
                ->cannotBeEmpty()
                ->info('The matomo API KEY')
            ->end()
            ->scalarNode('matomoSiteId')
                ->defaultValue('${MATOMO_SITE_ID}')
                ->cannotBeEmpty()
                ->isRequired()
            ->end()
        ;
    }

    public function handleUserAction(string $action, Request $request, IntegrationConfig $config): ?Response
    {
        switch ($action) {
            case self::ACTION_PROCESS:
                $data = $this->matomoProcessor->process($request->get('trackingId'), $request->get('type'), $config);
                if (!isset($data[0])) {
                    return new Response('{}', Response::HTTP_OK);
                }

                return new Response(json_encode($data[0]), Response::HTTP_OK, ['Content-Type' => 'application/json']);
            default:
                throw new \InvalidArgumentException(sprintf('Unsupported action "%s"', $action));
        }

        return null;
    }

    public static function getTitle(): string
    {
        return 'Matomo';
    }

    public static function getName(): string
    {
        return 'matomo';
    }

    public function getSupportedContexts(): array
    {
        return [IntegrationContext::AssetView];
    }
}
