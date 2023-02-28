<?php

declare(strict_types=1);

namespace App\Integration;

use App\Consumer\Handler\Asset\NewAssetIntegrationHandler;
use App\Entity\Core\Asset;
use App\Entity\Core\File;
use App\Entity\Integration\WorkspaceIntegration;
use Arthem\Bundle\RabbitBundle\Producer\EventProducer;
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
    private EventProducer $eventProducer;

    public function __construct(
        IntegrationRegistry $integrationRegistry,
        EntityManagerInterface $em,
        EventProducer $eventProducer
    ) {
        $this->integrationRegistry = $integrationRegistry;
        $this->em = $em;
        $this->eventProducer = $eventProducer;
    }

    public function handleAsset(Asset $asset): void
    {
        if (null === $asset->getSource()) {
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
                $this->eventProducer->publish(NewAssetIntegrationHandler::createEvent(
                    $asset->getId(),
                    $workspaceIntegration->getId()
                ));
            }
        }
    }

    public function handleAssetIntegration(Asset $asset, WorkspaceIntegration $workspaceIntegration): void
    {
        if (null === $asset->getSource()) {
            throw new InvalidArgumentException(sprintf('Asset "%s" has no file', $asset->getId()));
        }

        $config = $this->getIntegrationConfiguration($workspaceIntegration);
        /** @var AssetOperationIntegrationInterface $integration */
        $integration = $config['integration'];
        if ($integration->supportsAsset($asset, $config)) {
            $integration->handleAsset($asset, $config);
        }
    }

    public function callIntegrationFunction(string $integrationId, string $func, array $args): void
    {
        $workspaceIntegration = $this->loadIntegration($integrationId);
        $integration = $this->integrationRegistry->getStrictIntegration($workspaceIntegration->getIntegration());
        $config = $this->getConfiguration($workspaceIntegration, $integration);

        call_user_func([$integration, $func], $config, $args);
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
        $config['integrationId'] = $workspaceIntegration->getId();
        $config['workspaceId'] = $workspaceIntegration->getWorkspaceId();

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
