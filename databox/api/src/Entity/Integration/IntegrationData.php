<?php

declare(strict_types=1);

namespace App\Entity\Integration;

use App\Entity\AbstractUuidEntity;
use App\Entity\Core\File;
use App\Entity\Traits\CreatedAtTrait;
use App\Entity\Traits\UpdatedAtTrait;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * @ORM\Entity(repositoryClass="App\Repository\Core\AssetRepository")
 * @ORM\Table(indexes={@ORM\Index(name="name", columns={"integration_id", "file_id", "name"})})
 */
class IntegrationData extends AbstractUuidEntity
{
    use CreatedAtTrait;
    use UpdatedAtTrait;

    /**
     * @ORM\ManyToOne(targetEntity=WorkspaceIntegration::class)
     * @ORM\JoinColumn(nullable=false)
     */
    private ?WorkspaceIntegration $integration = null;

    /**
     * @ORM\ManyToOne(targetEntity=File::class)
     * @ORM\JoinColumn(nullable=true)
     */
    private ?File $file = null;

    /**
     * @ORM\Column(type="string", length=100, nullable=false)
     * @Groups({"integrationdata:index"})
     */
    private ?string $name = null;

    /**
     * @ORM\Column(type="text", nullable=false)
     * @Groups({"integrationdata:index"})
     */
    private ?string $value = null;

    public function getIntegration(): ?WorkspaceIntegration
    {
        return $this->integration;
    }

    public function setIntegration(?WorkspaceIntegration $integration): void
    {
        $this->integration = $integration;
    }

    public function getFile(): ?File
    {
        return $this->file;
    }

    public function setFile(?File $file): void
    {
        $this->file = $file;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): void
    {
        $this->name = $name;
    }

    public function getValue(): ?string
    {
        return $this->value;
    }

    public function setValue(?string $value): void
    {
        $this->value = $value;
    }
}
