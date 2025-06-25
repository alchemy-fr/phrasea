<?php

declare(strict_types=1);

namespace App\Api\Model\Output;

use App\Api\Model\Output\Traits\CreatedAtDTOTrait;
use App\Api\Model\Output\Traits\UpdatedAtDTOTrait;
use App\Entity\Integration\IntegrationData;
use App\Entity\Integration\WorkspaceIntegration;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Serializer\Annotation\MaxDepth;

class WorkspaceIntegrationOutput extends AbstractUuidOutput
{
    use CreatedAtDTOTrait;
    use UpdatedAtDTOTrait;

    #[Groups([WorkspaceIntegration::GROUP_LIST])]
    private ?string $title = null;

    #[MaxDepth(1)]
    #[Groups([WorkspaceIntegration::GROUP_LIST])]
    public $workspace;

    #[Groups([WorkspaceIntegration::GROUP_LIST])]
    private ?string $integration = null;

    #[Groups([WorkspaceIntegration::GROUP_LIST])]
    public ?string $integrationTitle = null;

    #[Groups([WorkspaceIntegration::GROUP_LIST])]
    public ?string $configYaml = null;

    #[Groups([WorkspaceIntegration::GROUP_LIST])]
    private bool $enabled = true;

    #[Groups([WorkspaceIntegration::GROUP_LIST])]
    public ?array $lastErrors = null;

    #[Groups([WorkspaceIntegration::GROUP_LIST])]
    private ?array $tokens = null;

    #[Groups([WorkspaceIntegration::GROUP_LIST])]
    public ?array $needs = null;

    #[Groups([WorkspaceIntegration::GROUP_LIST])]
    public ?string $if = null;

    /**
     * @var IntegrationData[]
     */
    #[Groups([WorkspaceIntegration::GROUP_LIST])]
    private array $data = [];

    /**
     * Client options.
     */
    #[Groups([WorkspaceIntegration::GROUP_LIST])]
    private array $config = [];

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(?string $title): void
    {
        $this->title = $title;
    }

    public function getIntegration(): ?string
    {
        return $this->integration;
    }

    public function setIntegration(?string $integration): void
    {
        $this->integration = $integration;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function setEnabled(bool $enabled): void
    {
        $this->enabled = $enabled;
    }

    public function getData(): array
    {
        return $this->data;
    }

    public function setData(array $data): void
    {
        $this->data = $data;
    }

    public function getConfig(): array
    {
        return $this->config;
    }

    public function setConfig(array $config): void
    {
        $this->config = $config;
    }

    public function getTokens(): ?array
    {
        return $this->tokens;
    }

    public function setTokens(?array $tokens): void
    {
        $this->tokens = $tokens;
    }
}
