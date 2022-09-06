<?php

declare(strict_types=1);

namespace App\Entity\Integration;

use App\Entity\AbstractUuidEntity;
use App\Entity\Traits\CreatedAtTrait;
use App\Entity\Traits\UpdatedAtTrait;
use App\Entity\Traits\WorkspaceTrait;
use Doctrine\ORM\Mapping as ORM;
use GuzzleHttp\Exception\InvalidArgumentException;

/**
 * @ORM\Entity(repositoryClass="App\Repository\Core\AssetRepository")
 * @ORM\Table(uniqueConstraints={@ORM\UniqueConstraint(name="uniq_integration_key",columns={"workspace_id", "title", "integration"})})
 */
class WorkspaceIntegration extends AbstractUuidEntity
{
    use CreatedAtTrait;
    use UpdatedAtTrait;
    use WorkspaceTrait;

    /**
     * @ORM\Column(type="string", length=255, nullable=false)
     */
    private ?string $title = null;

    /**
     * @ORM\Column(type="string", length=100, nullable=false)
     */
    private ?string $integration = null;

    /**
     * @ORM\Column(type="boolean", nullable=false)
     */
    private bool $enabled = true;

    /**
     * @ORM\Column(type="json", nullable=false)
     */
    private array $options = [];

    private ?string $optionsJson = null;

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

    public function getOptions(): array
    {
        return $this->options;
    }

    public function setOptions(array $options): void
    {
        $this->options = $options;
    }

    public function getOptionsJson(): string
    {
        if (null !== $this->optionsJson) {
            return $this->optionsJson;
        }

        return \GuzzleHttp\json_encode($this->options, JSON_PRETTY_PRINT);
    }

    public function setOptionsJson(string $options): void
    {
        $this->optionsJson = $options;
        try {
            $this->options = \GuzzleHttp\json_decode($options, true);
        } catch (InvalidArgumentException $e) {
        }
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function setEnabled(bool $enabled): void
    {
        $this->enabled = $enabled;
    }

    public function getThis(): self
    {
        return $this;
    }
}
