<?php

namespace App\Fixture;

use App\Entity\Core\AttributePolicy;
use App\Entity\Core\RenditionDefinition;
use App\Entity\Core\RenditionPolicy;
use App\Entity\Core\Workspace;
use App\Entity\Integration\WorkspaceIntegration;
use App\Service\Workspace\WorkspaceCreator;
use App\Service\Workspace\WorkspaceDefaults;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;

#[Autoconfigure(public: true)]
final readonly class WorkspaceFactory
{
    /**
     * @var \SplObjectStorage<Workspace, WorkspaceDefaults>
     */
    private \SplObjectStorage $defaults;

    public function __construct(
        private WorkspaceCreator $workspaceCreator,
    ) {
        $this->defaults = new \SplObjectStorage();
    }

    public function create(string $ownerId): Workspace
    {
        $workspace = new Workspace();
        $workspace->setOwnerId($ownerId);
        $workspace->setFileAnalysisRequired(true);
        $this->defaults[$workspace] = $this->workspaceCreator->createWorkspace($workspace);

        return $workspace;
    }

    public function getRenditionPolicy(Workspace $workspace): RenditionPolicy
    {
        return $this->getDefaults($workspace)->renditionPolicy;
    }

    public function getRenditionDefinition(Workspace $workspace, string $key): RenditionDefinition
    {
        $definitions = $this->getDefaults($workspace)->renditionDefinitions;

        return $definitions[$key] ?? throw new \InvalidArgumentException(sprintf('Unknown rendition definition "%s" (available: %s)', $key, implode(', ', array_keys($definitions))));
    }

    public function getAttributePolicy(Workspace $workspace): AttributePolicy
    {
        return $this->getDefaults($workspace)->attributePolicy;
    }

    public function getIntegration(Workspace $workspace, string $key): WorkspaceIntegration
    {
        $integrations = $this->getDefaults($workspace)->integrations;

        return $integrations[$key] ?? throw new \InvalidArgumentException(sprintf('Unknown integration "%s" (available: %s)', $key, implode(', ', array_keys($integrations))));
    }

    private function getDefaults(Workspace $workspace): WorkspaceDefaults
    {
        if (!$this->defaults->contains($workspace)) {
            throw new \LogicException(sprintf('Workspace "%s" was not created through %s.', $workspace->getName() ?? '?', self::class));
        }

        return $this->defaults[$workspace];
    }
}
