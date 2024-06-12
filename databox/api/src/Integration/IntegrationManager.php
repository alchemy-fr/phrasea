<?php

declare(strict_types=1);

namespace App\Integration;

use App\Entity\Integration\WorkspaceIntegration;
use App\Integration\Env\EnvResolver;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\Dumper\YamlReferenceDumper;
use Symfony\Component\Config\Definition\NodeInterface;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

readonly class IntegrationManager
{
    public function __construct(
        private IntegrationRegistry $integrationRegistry,
        private EntityManagerInterface $em,
        private EnvResolver $envResolver,
    ) {
    }

    public function callIntegrationFunction(string $integrationId, string $func, array $args): void
    {
        $workspaceIntegration = $this->loadIntegration($integrationId);
        $integration = $this->integrationRegistry->getStrictIntegration($workspaceIntegration->getIntegration());
        $config = $this->getConfiguration($workspaceIntegration, $integration);

        call_user_func([$integration, $func], $config, $args);
    }

    public function handleAction(WorkspaceIntegration $workspaceIntegration, string $action, Request $request): Response
    {
        $integration = $this->integrationRegistry->getStrictIntegration($workspaceIntegration->getIntegration());
        if (!$integration instanceof UserActionsIntegrationInterface) {
            throw new \InvalidArgumentException(sprintf('Integration "%s" does not support file actions', $workspaceIntegration->getIntegration()));
        }

        $config = $this->getConfiguration($workspaceIntegration, $integration);

        return $integration->handleUserAction($action, $request, $config) ?? new JsonResponse();
    }

    public function loadIntegration(string $id): WorkspaceIntegration
    {
        $integration = $this->em->find(WorkspaceIntegration::class, $id);
        if (!$integration instanceof WorkspaceIntegration) {
            throw new \InvalidArgumentException(sprintf('Workspace integration "%s" not found', $id));
        }

        return $integration;
    }

    public function findIntegrationsOfContext(IntegrationContext $context): array
    {
        $types = $this->integrationRegistry->getSupportingIntegrations($context);

        return $this->em->getRepository(WorkspaceIntegration::class)
            ->findBy([
                'integration' => array_map(fn (IntegrationInterface $integration): string => $integration::getName(), $types),
                'enabled' => true,
            ]);
    }

    public function getIntegrationConfiguration(WorkspaceIntegration $workspaceIntegration): IntegrationConfig
    {
        return $this->getConfiguration(
            $workspaceIntegration,
            $this->integrationRegistry->getStrictIntegration($workspaceIntegration->getIntegration())
        );
    }

    public function validateIntegration(WorkspaceIntegration $workspaceIntegration): void
    {
        $integration = $this->integrationRegistry->getStrictIntegration($workspaceIntegration->getIntegration());

        $config = $this->getConfiguration(
            $workspaceIntegration,
            $integration
        );

        $integration->validateConfiguration($config);
    }

    public function getIntegrationConfigInfo(WorkspaceIntegration $workspaceIntegration): array
    {
        $integration = $this->integrationRegistry->getStrictIntegration($workspaceIntegration->getIntegration());
        $config = $this->getConfiguration(
            $workspaceIntegration,
            $integration
        );

        return $integration->getConfigurationInfo($config);
    }

    private function buildConfiguration(IntegrationInterface $integration): NodeInterface
    {
        $treeBuilder = new TreeBuilder('root');
        $integration->buildConfiguration($treeBuilder->getRootNode()->children());

        return $treeBuilder->buildTree();
    }

    public function getIntegrationReference(IntegrationInterface $integration): string
    {
        $node = $this->buildConfiguration($integration);
        $dumper = new YamlReferenceDumper();

        $output = $dumper->dumpNode($node);
        $output = preg_replace("#^root:(\n( {4})?|\s+\[])#", '', (string) $output);
        $output = preg_replace("#\n {4}#", "\n", $output);
        $output = preg_replace("#\n\n#", "\n", $output);

        return trim(preg_replace("#^\n+#", '', $output));
    }

    private function getConfiguration(WorkspaceIntegration $workspaceIntegration, IntegrationInterface $integration): IntegrationConfig
    {
        $node = $this->buildConfiguration($integration);

        $processor = new Processor();
        $config = $processor->process($node, ['root' => $workspaceIntegration->getConfig()]);

        return new IntegrationConfig(
            $config,
            $workspaceIntegration,
            $integration,
            $this->envResolver,
        );
    }
}
