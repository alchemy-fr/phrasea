<?php

declare(strict_types=1);

namespace App\Entity\Core;

use ApiPlatform\Core\Annotation\ApiResource;
use App\Entity\AbstractUuidEntity;
use App\Entity\Traits\CreatedAtTrait;
use App\Entity\Traits\UpdatedAtTrait;
use App\Entity\Traits\WorkspaceTrait;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * @ORM\Entity()
 * @ApiResource()
 */
class File extends AbstractUuidEntity
{
    use CreatedAtTrait;
    use UpdatedAtTrait;
    use WorkspaceTrait;
    public const STORAGE_S3_MAIN = 's3_main';
    public const STORAGE_URL = 'url';

    /**
     * Override trait for annotation.
     *
     * @ORM\ManyToOne(targetEntity="App\Entity\Core\Workspace", inversedBy="files")
     * @ORM\JoinColumn(nullable=false)
     * @Groups({"_"})
     */
    protected ?Workspace $workspace = null;

    /**
     * The MIME type.
     *
     * @ORM\Column(type="string", length=100, nullable=true)
     */
    private ?string $type = null;

    /**
     * @ORM\Column(type="bigint", nullable=true)
     */
    private ?int $size = null;

    /**
     * @ORM\Column(type="string", length=64, nullable=true)
     */
    private ?string $checksum = null;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private ?string $path = null;

    /**
     * Is path accessible from browser.
     *
     * @ORM\Column(type="boolean", nullable=false)
     */
    private bool $pathPublic = true;

    /**
     * @ORM\Column(type="string", length=150, nullable=false)
     */
    private ?string $storage = null;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private ?string $originalName = null;

    /**
     * @ORM\Column(type="string", length=20, nullable=true)
     */
    private ?string $extension = null;

    /**
     * @ORM\Column(type="array", nullable=true)
     */
    private ?array $alternateUrls = null;

    /**
     * Normalized metadata.
     *
     * @ORM\Column(type="json", nullable=true)
     */
    private ?array $metadata = null;

    public function getPath(): ?string
    {
        return $this->path;
    }

    public function setPath(?string $path): void
    {
        $this->path = $path;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(?string $type): void
    {
        $this->type = $type;
    }

    public function getSize(): ?int
    {
        return $this->size;
    }

    public function setSize(?int $size): void
    {
        $this->size = $size;
    }

    public function __toString()
    {
        return $this->getId();
    }

    public function getStorage(): ?string
    {
        return $this->storage;
    }

    public function setStorage(?string $storage): void
    {
        $this->storage = $storage;
    }

    public function getAlternateUrls(): ?array
    {
        return $this->alternateUrls;
    }

    public function setAlternateUrl(string $type, string $url): void
    {
        $this->alternateUrls[$type] = $url;
    }

    public function isPathPublic(): bool
    {
        return $this->pathPublic;
    }

    public function setPathPublic(bool $pathPublic): void
    {
        $this->pathPublic = $pathPublic;
    }

    public function setAlternateUrls(?array $alternateUrls): void
    {
        $this->alternateUrls = $alternateUrls;
    }

    public function getChecksum(): ?string
    {
        return $this->checksum;
    }

    public function setChecksum(?string $checksum): void
    {
        $this->checksum = $checksum;
    }

    public function getFilename(): string
    {
        return $this->originalName ?? sprintf('%s.%s', $this->getId(), $this->getExtension());
    }

    public function getOriginalName(): ?string
    {
        return $this->originalName;
    }

    public function setOriginalName(?string $originalName): void
    {
        $this->originalName = $originalName;
    }

    public function getExtension(): ?string
    {
        return $this->extension;
    }

    public function getExtensionWithDot(): string
    {
        return $this->extension ? '.'.$this->extension : '';
    }

    public function setExtension(?string $extension): void
    {
        $this->extension = $extension;
    }

    public function getMetadata(): ?array
    {
        return $this->metadata;
    }

    public function setMetadata(?array $metadata): void
    {
        $this->metadata = $metadata;
    }
}
