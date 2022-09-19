<?php

declare(strict_types=1);

namespace App\Integration;

use App\Entity\Core\Asset;
use App\Entity\Core\File;
use App\Entity\Integration\WorkspaceIntegration;
use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\OptionsResolver\OptionsResolver;

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

            $options = $this->resolveOptions($workspaceIntegration, $integration);
            if ($integration->supportsAsset($asset, $options)) {
                $integration->handleAsset($asset, $options);
            }
        }
    }

    public function handleFileAction(WorkspaceIntegration $workspaceIntegration, string $action, Request $request, File $file): Response
    {
        $integration = $this->integrationRegistry->getStrictIntegration($workspaceIntegration->getIntegration());
        if (!$integration instanceof FileActionsIntegrationInterface) {
            throw new InvalidArgumentException(sprintf('Integration "%s" does not support file actions', $workspaceIntegration->getIntegration()));
        }

        return $integration->handleFileAction($action, $request, $file, $this->resolveOptions($workspaceIntegration, $integration));
    }

    public function loadIntegration(string $id): WorkspaceIntegration
    {
        $integration = $this->em->find(WorkspaceIntegration::class, $id);
        if (!$integration instanceof WorkspaceIntegration) {
            throw new InvalidArgumentException(sprintf('Workspace integration "%s" not found', $id));
        }

        return $integration;
    }

    public function getIntegrationOptions(WorkspaceIntegration $workspaceIntegration): array
    {
        return $this->resolveOptions(
            $workspaceIntegration,
            $this->integrationRegistry->getStrictIntegration($workspaceIntegration->getIntegration())
        );
    }

    public function getIntegrationConfigInfo(WorkspaceIntegration $workspaceIntegration): array
    {
        $integration = $this->integrationRegistry->getStrictIntegration($workspaceIntegration->getIntegration());
        $options = $this->resolveOptions(
            $workspaceIntegration,
            $integration
        );

        return $integration->getConfigurationInfo($options);
    }

    private function resolveOptions(WorkspaceIntegration $workspaceIntegration, IntegrationInterface $integration): array
    {
        $resolver = new OptionsResolver();
        $resolver->setDefault('integrationId', $workspaceIntegration->getId());
        $resolver->setDefault('workspaceIntegration', $workspaceIntegration);
        $resolver->setDefault('integration', $integration);
        $integration->configureOptions($resolver);

        return $resolver->resolve($workspaceIntegration->getOptions());
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
