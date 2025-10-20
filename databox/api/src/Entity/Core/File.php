<?php

declare(strict_types=1);

namespace App\Entity\Core;

use Alchemy\CoreBundle\Entity\AbstractUuidEntity;
use Alchemy\CoreBundle\Entity\Traits\CreatedAtTrait;
use Alchemy\CoreBundle\Entity\Traits\UpdatedAtTrait;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use App\Api\Model\Output\FileOutput;
use App\Entity\Traits\WorkspaceTrait;
use App\Security\Voter\AbstractVoter;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

#[ApiResource(
    shortName: 'file',
    operations: [
        new Get(
            security: 'is_granted("'.AbstractVoter::READ.'", object)',
        ),
        new Get(
            uriTemplate: '/files/{id}/metadata',
            normalizationContext: ['groups' => [File::GROUP_METADATA]],
            security: 'is_granted("'.AbstractVoter::READ.'", object)',
            name: 'file_metadata',
        ),
    ],
    normalizationContext: [
        'groups' => [File::GROUP_LIST],
    ],
    denormalizationContext: [
        'groups' => [File::GROUP_WRITE],
    ],
    output: FileOutput::class
)]
#[ORM\Entity]
class File extends AbstractUuidEntity implements \Stringable
{
    use CreatedAtTrait;
    use UpdatedAtTrait;
    use WorkspaceTrait;
    final public const string GROUP_READ = 'file:r';
    final public const string GROUP_LIST = 'file:i';
    final public const string GROUP_METADATA = 'file:m';
    final public const string GROUP_WRITE = 'file:w';
    final public const string STORAGE_S3_MAIN = 's3_main';
    final public const string STORAGE_URL = 'url';

    final public const string ANALYSIS_SUCCESS = 'success';
    final public const string ANALYSIS_FAILED = 'failed';
    final public const string ANALYSIS_SKIPPED = 'skipped';

    /**
     * Override trait for annotation.
     */
    #[ORM\ManyToOne(targetEntity: Workspace::class, inversedBy: 'files')]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['_'])]
    protected ?Workspace $workspace = null;

    /**
     * The MIME type.
     */
    #[ORM\Column(type: Types::STRING, length: 100, nullable: true)]
    private ?string $type = null;

    #[ORM\Column(type: Types::BIGINT, nullable: true)]
    private string|int|null $size = null;

    #[ORM\Column(type: Types::STRING, length: 64, nullable: true)]
    private ?string $checksum = null;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: false)]
    private ?string $path = null;

    /**
     * Is path accessible from browser or worker.
     */
    #[ORM\Column(type: Types::BOOLEAN, nullable: false)]
    private bool $pathPublic = true;

    #[ORM\Column(type: Types::STRING, length: 150, nullable: false)]
    private ?string $storage = null;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $originalName = null;

    #[ORM\Column(type: Types::STRING, length: 20, nullable: true)]
    private ?string $extension = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $alternateUrls = null;

    /**
     * Normalized metadata.
     */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $metadata = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $analysis = null;

    public function getPath(): ?string
    {
        return $this->path;
    }

    public function setPath(?string $path): void
    {
        if (null !== $path && empty(trim($path))) {
            throw new \InvalidArgumentException('Empty path');
        }

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

    public function getSize(): string|int|null
    {
        return $this->size;
    }

    public function setSize(string|int|null $size): void
    {
        $this->size = $size;
    }

    public function __toString(): string
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

    public function getAnalysis(): ?array
    {
        return $this->analysis;
    }

    public function setAnalysis(?array $analysis): void
    {
        $this->analysis = $analysis;
    }

    public function isAnalysisPending(): bool
    {
        return null === $this->analysis;
    }

    public function isAnalyzed(): bool
    {
        return null !== $this->analysis;
    }

    public function isAccepted(): bool
    {
        return empty($this->analysis) || self::ANALYSIS_SUCCESS === ($this->analysis['status'] ?? null);
    }

    public function setNoAnalysisNeeded(): void
    {
        $this->analysis ??= [];
    }
}
