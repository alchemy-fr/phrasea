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
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Contracts\Service\ResetInterface;

/**
 * The face recognition configurations of the instance: where the faces are attached to the
 * assets (the attribute definition) and, through it, where the persons are (its entity list).
 * A workspace may run several integrations, each on its own attribute. Cached for the request.
 */
final class FaceRecognitionConfigResolver implements ResetInterface
{
    /** @var array<string, string|null> attribute definition IDs by workspace and slug */
    private array $definitions = [];

    /** @var array<string, IntegrationConfig>|null indexed by attribute definition ID */
    private ?array $byDefinition = null;

    /** @var array<string, IntegrationConfig>|null indexed by entity list ID */
    private ?array $byList = null;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly IntegrationManager $integrationManager,
        private readonly AttributeDefinitionRepository $attributeDefinitionRepository,
    ) {
    }

    /**
     * The attribute definition holding the faces of the configuration.
     */
    public function getDefinition(IntegrationConfig $config): AttributeDefinition
    {
        $key = $config->getWorkspaceId().'/'.$config['attribute'];
        if (!array_key_exists($key, $this->definitions)) {
            $this->definitions[$key] = $this->attributeDefinitionRepository->getAttributeDefinitionBySlug((string) $config->getWorkspaceId(), (string) $config['attribute'])?->getId();
        }

        // Fetched again each time: the cached ID survives a cleared entity manager, a cached entity would not
        $definition = null !== $this->definitions[$key] ? $this->em->find(AttributeDefinition::class, $this->definitions[$key]) : null;

        return $definition ?? throw new \InvalidArgumentException(sprintf('Attribute definition slug "%s" not found in workspace "%s"', $config['attribute'], $config->getWorkspaceId()));
    }

    /**
     * The entity list holding the persons of the configuration: the one of its attribute definition.
     */
    public function getEntityList(IntegrationConfig $config): EntityList
    {
        return $this->getDefinition($config)->getEntityList()
            ?? throw new \InvalidArgumentException(sprintf('Attribute "%s" is not bound to an entity list', $config['attribute']));
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
            if (null === $workspaceIntegration->getWorkspace()) {
                continue;
            }
            try {
                $config = $this->integrationManager->getIntegrationConfiguration($workspaceIntegration);
                $definition = $this->getDefinition($config);
            } catch (InvalidConfigurationException|\InvalidArgumentException) {
                // An incomplete configuration, or one pointing to a removed attribute, recognizes nothing
                continue;
            }
            $definitions[$definition->getId()] = $config;
            $list = $definition->getEntityList();
            if (null !== $list) {
                $lists[$list->getId()] = $config;
            }
        }

        $this->byDefinition = $definitions;
        $this->byList = $lists;

        return ['definitions' => $definitions, 'lists' => $lists];
    }

    public function reset(): void
    {
        $this->definitions = [];
        $this->byDefinition = null;
        $this->byList = null;
    }
}
