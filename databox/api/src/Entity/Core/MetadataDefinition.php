<?php

declare(strict_types=1);

namespace App\Entity\Core;

use App\Entity\AbstractUuidEntity;
use App\Entity\Traits\CreatedAtTrait;
use App\Entity\Traits\UpdatedAtTrait;
use App\Entity\Traits\WorkspaceTrait;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity()
 */
class MetadataDefinition extends AbstractUuidEntity
{
    use CreatedAtTrait;
    use UpdatedAtTrait;
    use WorkspaceTrait;

    /**
     * @ORM\Column(type="string", length=100, nullable=false)
     */
    private string $name;

    /**
     * Apply this definition to files of this MIME type.
     * If null, applied to all files.
     *
     * @ORM\Column(type="string", length=100, nullable=true)
     */
    private ?string $fileType = null;

    /**
     * @ORM\Column(type="string", length=50, nullable=false)
     */
    private string $fieldType = 'text';

    /**
     * Value can be manually set by user.
     *
     * @ORM\Column(type="boolean")
     */
    private bool $editable = true;

    /**
     * Resolve to this technical data if no user value provided.
     *
     * @ORM\Column(type="array", nullable=true)
     */
    private ?array $fallbacks = null;

    public function getFileType(): ?string
    {
        return $this->fileType;
    }

    public function setFileType(?string $fileType): void
    {
        $this->fileType = $fileType;
    }

    public function getFieldType(): string
    {
        return $this->fieldType;
    }

    public function setFieldType(string $fieldType): void
    {
        $this->fieldType = $fieldType;
    }

    public function isEditable(): bool
    {
        return $this->editable;
    }

    public function setEditable(bool $editable): void
    {
        $this->editable = $editable;
    }

    public function getFallbacks(): ?array
    {
        return $this->fallbacks;
    }

    public function setFallbacks(?array $fallbacks): void
    {
        $this->fallbacks = $fallbacks;
    }
}
