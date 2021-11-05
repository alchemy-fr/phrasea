<?php

declare(strict_types=1);

namespace App\Entity\Core;

use ApiPlatform\Core\Annotation\ApiProperty;
use ApiPlatform\Core\Annotation\ApiResource;
use App\Entity\AbstractUuidEntity;
use App\Entity\Traits\CreatedAtTrait;
use App\Entity\Traits\UpdatedAtTrait;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * @ORM\Entity(repositoryClass="App\Repository\Core\SubDefinitionRepository")
 * @ORM\Table(uniqueConstraints={@ORM\UniqueConstraint(name="uniq_sub_def",columns={"specification_id", "asset_id"})})
 * @ApiResource()
 */
class SubDefinition extends AbstractUuidEntity
{
    use CreatedAtTrait;
    use UpdatedAtTrait;

    /**
     * @Groups({"subdef:index", "subdef:read"})
     * @ORM\ManyToOne(targetEntity="App\Entity\Core\SubDefinitionSpec")
     * @ORM\JoinColumn(nullable=false)
     */
    private ?SubDefinitionSpec $specification = null;

    /**
     * @Groups({"subdef:index", "subdef:read"})
     * @ORM\ManyToOne(targetEntity="App\Entity\Core\Asset")
     * @ORM\JoinColumn(nullable=false)
     */
    private ?Asset $asset = null;

    /**
     * @Groups({"subdef:index", "subdef:read"})
     * @ORM\ManyToOne(targetEntity="App\Entity\Core\File")
     * @ORM\JoinColumn(nullable=false)
     */
    private ?File $file = null;

    /**
     * @Groups({"subdef:index", "subdef:read"})
     * @ORM\Column(type="boolean")
     */
    private bool $ready = false;

    public function getAsset(): Asset
    {
        return $this->asset;
    }

    public function setAsset(Asset $asset): void
    {
        $this->asset = $asset;
    }

    public function getFile(): File
    {
        return $this->file;
    }

    public function setFile(File $file): void
    {
        $this->file = $file;
    }

    public function getSpecification(): SubDefinitionSpec
    {
        return $this->specification;
    }

    public function setSpecification(SubDefinitionSpec $specification): void
    {
        $this->specification = $specification;
    }

    /**
     * @ApiProperty()
     * @Groups({"subdef:index", "subdef:read"})
     */
    public function getName(): string
    {
        return $this->specification->getName();
    }

    public function isReady(): bool
    {
        return $this->ready;
    }

    public function setReady(bool $ready): void
    {
        $this->ready = $ready;
    }
}
