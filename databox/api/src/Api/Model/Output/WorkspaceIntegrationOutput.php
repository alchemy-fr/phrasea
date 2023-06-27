<?php

declare(strict_types=1);

namespace App\Api\Model\Output;

use App\Api\Model\Output\Traits\CreatedAtDTOTrait;
use App\Api\Model\Output\Traits\UpdatedAtDTOTrait;
use App\Entity\Integration\IntegrationData;
use Symfony\Component\Serializer\Annotation\Groups;

class WorkspaceIntegrationOutput extends AbstractUuidOutput
{
    use CreatedAtDTOTrait;
    use UpdatedAtDTOTrait;

    #[Groups(['integration:index'])]
    private ?string $title = null;

    #[Groups(['integration:index'])]
    private ?string $integration = null;

    #[Groups(['integration:index'])]
    private bool $enabled = true;

    #[Groups(['integration:index'])]
    private ?bool $supported = null;

    /**
     * @var IntegrationData[]
     */
    #[Groups(['integration:index'])]
    private array $data = [];

    /**
     * Client options.
     */
    #[Groups(['integration:index'])]
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

    public function getSupported(): ?bool
    {
        return $this->supported;
    }

    public function setSupported(?bool $supported): void
    {
        $this->supported = $supported;
    }
}
