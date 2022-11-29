<?php

declare(strict_types=1);

namespace App\Integration;

use App\Entity\Core\Asset;
use App\Entity\Core\File;
use App\Entity\Integration\WorkspaceIntegration;
use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\Dumper\YamlReferenceDumper;
use Symfony\Component\Config\Definition\NodeInterface;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class IntegrationManager
{
    private IntegrationRegistry $integrationRegistry;
    private EntityManagerInterface $em;

    public function __construct(IntegrationRegistry $integrationRegistry, EntityManagerInterface $em)
    {
        $this->integrationRegistry = $integrationRegistry;
        $this->em = $em;
    }

    public function handleAsset(Asset $asset): void
    {
        if (null === $asset->getFile()) {
            throw new InvalidArgumentException(sprintf('Asset "%s" has no file', $asset->getId()));
        }

        /** @var AssetOperationIntegrationInterface[] $integrations */
        $integrations = $this->getIntegrationsOfType($asset->getWorkspaceId(), AssetOperationIntegrationInterface::class);

        foreach ($integrations as $integration) {
            /** @var AssetOperationIntegrationInterface $integration */
            /** @var WorkspaceIntegration $workspaceIntegration */
            [$integration, $workspaceIntegration] = $integration;

            $config = $this->getConfiguration($workspaceIntegration, $integration);
            if ($integration->supportsAsset($asset, $config)) {
                $integration->handleAsset($asset, $config);
            }
        }
    }

    public function handleFileAction(WorkspaceIntegration $workspaceIntegration, string $action, Request $request, File $file): Response
    {
        $integration = $this->integrationRegistry->getStrictIntegration($workspaceIntegration->getIntegration());
        if (!$integration instanceof FileActionsIntegrationInterface) {
            throw new InvalidArgumentException(sprintf('Integration "%s" does not support file actions', $workspaceIntegration->getIntegration()));
        }

        $config = $this->getConfiguration($workspaceIntegration, $integration);
        if (!$integration->supportsFileActions($file, $config)) {
            throw new BadRequestHttpException(sprintf('Unsupported actions on file "%s"', $file->getId()));
        }

        return $integration->handleFileAction($action, $request, $file, $config);
    }

    public function loadIntegration(string $id): WorkspaceIntegration
    {
        $integration = $this->em->find(WorkspaceIntegration::class, $id);
        if (!$integration instanceof WorkspaceIntegration) {
            throw new InvalidArgumentException(sprintf('Workspace integration "%s" not found', $id));
        }

        return $integration;
    }

    public function getIntegrationConfiguration(WorkspaceIntegration $workspaceIntegration): array
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
        $output = preg_replace("#^root:(\n( {4})?|\s+\[])#", '', $output);
        $output = preg_replace("#\n {4}#", "\n", $output);
        $output = preg_replace("#\n\n#", "\n", $output);

        return trim(preg_replace("#^\n+#", '', $output));
    }

    private function getConfiguration(WorkspaceIntegration $workspaceIntegration, IntegrationInterface $integration): array
    {
        $node = $this->buildConfiguration($integration);
        $processor = new Processor();

        $config = $processor->process($node, ['root' => $workspaceIntegration->getConfig()]);

        $config['integration'] = $integration;
        $config['workspaceIntegration'] = $workspaceIntegration;

        return $config;
    }

    /**
     * @return Array<IntegrationInterface, WorkspaceIntegration>[]
     */
    private function getIntegrationsOfType(string $workspaceId, string $interface): array
    {
        /** @var WorkspaceIntegration[] $workspaceIntegrations */
        $workspaceIntegrations = $this->em->getRepository(WorkspaceIntegration::class)
            ->findBy([
                'workspace' => $workspaceId,
                'enabled' => true,
            ]);

        $result = [];
        foreach ($workspaceIntegrations as $workspaceIntegration) {
            $integration = $this->integrationRegistry->getStrictIntegration($workspaceIntegration->getIntegration());

            if (is_subclass_of($integration, $interface)) {
                $result[] = [$integration, $workspaceIntegration];
            }
        }

        return $result;
    }
}
