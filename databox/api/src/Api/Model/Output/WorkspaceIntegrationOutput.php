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

    /**
     * @Groups({"integration:index"})
     */
    private ?string $title = null;

    /**
     * @Groups({"integration:index"})
     */
    private ?string $integration = null;

    /**
     * @Groups({"integration:index"})
     */
    private bool $enabled = true;

    /**
     * @var IntegrationData[]
     *
     * @Groups({"integration:index"})
     */
    private array $data = [];

    /**
     * Client options.
     *
     * @var array
     *
     * @Groups({"integration:index"})
     */
    private array $options = [];

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

    public function getOptions(): array
    {
        return $this->options;
    }

    public function setOptions(array $options): void
    {
        $this->options = $options;
    }
}
