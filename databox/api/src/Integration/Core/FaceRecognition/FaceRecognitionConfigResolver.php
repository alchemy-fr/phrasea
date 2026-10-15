<?php

declare(strict_types=1);

namespace App\Integration\Core\FaceRecognition;

use App\Entity\Core\AttributeDefinition;
use App\Entity\Core\EntityList;
use App\Entity\Integration\WorkspaceIntegration;
use App\Integration\IntegrationConfig;
use App\Integration\IntegrationManager;
use App\Repository\Core\AttributeDefinitionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Contracts\Service\ResetInterface;

/**
 * The face recognition configuration of the workspaces: where the persons are (entity list) and
 * how they are attached to the assets (attribute definition). Cached for the request.
 */
final class FaceRecognitionConfigResolver implements ResetInterface
{
    /** @var array<string, IntegrationConfig|null> */
    private array $byWorkspace = [];

    /** @var array<string, AttributeDefinition|null> */
    private array $definitions = [];

    /** @var array<string, IntegrationConfig|null>|null */
    private ?array $byDefinition = null;

    /** @var array<string, IntegrationConfig|null>|null */
    private ?array $byList = null;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly IntegrationManager $integrationManager,
        private readonly AttributeDefinitionRepository $attributeDefinitionRepository,
    ) {
    }

    /**
     * The enabled face recognition integration of the workspace, or null.
     */
    public function getConfig(string $workspaceId): ?IntegrationConfig
    {
        if (array_key_exists($workspaceId, $this->byWorkspace)) {
            return $this->byWorkspace[$workspaceId];
        }

        // TODO there can be many integrations
        $workspaceIntegration = $this->em->getRepository(WorkspaceIntegration::class)->findOneBy([
            'workspace' => $workspaceId,
            'integration' => FaceRecognitionIntegration::getName(),
            'enabled' => true,
        ]);

        return $this->byWorkspace[$workspaceId] = $workspaceIntegration instanceof WorkspaceIntegration
            ? $this->integrationManager->getIntegrationConfiguration($workspaceIntegration)
            : null;
    }

    /**
     * The attribute definition holding the faces of the workspace of the configuration.
     */
    public function getDefinition(IntegrationConfig $config): AttributeDefinition
    {
        $key = $config->getWorkspaceId().'/'.$config['attribute'];
        if (!array_key_exists($key, $this->definitions)) {
            $this->definitions[$key] = $this->attributeDefinitionRepository->getAttributeDefinitionBySlug($config->getWorkspaceId(), (string) $config['attribute']);
        }

        return $this->definitions[$key] ?? throw new \InvalidArgumentException(sprintf('Attribute definition slug "%s" not found in workspace "%s"', $config['attribute'], $config->getWorkspaceId()));
    }

    public function getEntityList(IntegrationConfig $config): EntityList
    {
        return $this->em->find(EntityList::class, (string) $config['entityList'])
            ?? throw new \InvalidArgumentException(sprintf('Entity list "%s" not found', $config['entityList']));
    }

    /**
     * The configuration whose faces live in the attribute definition, if any (null for any other definition).
     */
    public function getConfigOfDefinition(AttributeDefinition $definition): ?IntegrationConfig
    {
        $this->byDefinition ??= $this->indexConfigs()['definitions'];

        return $this->byDefinition[$definition->getId()] ?? null;
    }

    /**
     * The configuration whose persons live in the entity list, if any.
     */
    public function getConfigOfList(EntityList $list): ?IntegrationConfig
    {
        $this->byList ??= $this->indexConfigs()['lists'];

        return $this->byList[$list->getId()] ?? null;
    }

    /**
     * @return array{definitions: array<string, IntegrationConfig>, lists: array<string, IntegrationConfig>}
     */
    private function indexConfigs(): array
    {
        $definitions = [];
        $lists = [];
        $workspaceIntegrations = $this->em->getRepository(WorkspaceIntegration::class)->findBy([
            'integration' => FaceRecognitionIntegration::getName(),
            'enabled' => true,
        ]);
        foreach ($workspaceIntegrations as $workspaceIntegration) {
            $workspaceId = $workspaceIntegration->getWorkspace()?->getId();
            if (null === $workspaceId) {
                continue;
            }
            $config = $this->byWorkspace[$workspaceId] ??= $this->integrationManager->getIntegrationConfiguration($workspaceIntegration);
            if (null === $config) {
                continue;
            }
            $definition = $this->attributeDefinitionRepository->getAttributeDefinitionBySlug($workspaceId, (string) $config['attribute']);
            if (null !== $definition) {
                $definitions[$definition->getId()] = $config;
            }
            if (!empty($config['entityList'])) {
                $lists[(string) $config['entityList']] = $config;
            }
        }

        $this->byDefinition = $definitions;
        $this->byList = $lists;

        return ['definitions' => $definitions, 'lists' => $lists];
    }

    public function reset(): void
    {
        $this->byWorkspace = [];
        $this->definitions = [];
        $this->byDefinition = null;
        $this->byList = null;
    }
}
