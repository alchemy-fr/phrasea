<?php

declare(strict_types=1);

namespace App\Entity\Core;

use Alchemy\CoreBundle\Entity\AbstractUuidEntity;
use Alchemy\CoreBundle\Entity\Traits\CreatedAtTrait;
use Alchemy\CoreBundle\Entity\Traits\UpdatedAtTrait;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use App\Api\Model\Output\AssetDuplicateOutput;
use App\Api\Model\Output\FileOutput;
use App\Api\Provider\FileDuplicatesProvider;
use App\Entity\Traits\WorkspaceTrait;
use App\Repository\Core\FileRepository;
use App\Security\Voter\AbstractVoter;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Doctrine\UuidType;
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
        new Get(
            uriTemplate: '/files/{id}/duplicates',
            normalizationContext: [
                'groups' => [Asset::GROUP_LIST, 'dates'],
            ],
            output: AssetDuplicateOutput::class,
            name: 'file_duplicates',
            provider: FileDuplicatesProvider::class,
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
#[ORM\Entity(repositoryClass: FileRepository::class)]
#[ORM\Index(columns: ['workspace_id', 'checksum'])]
#[ORM\Index(columns: ['workspace_id', 'doc_unique_id'])]
class File extends AbstractUuidEntity implements \Stringable
{
    use CreatedAtTrait;
    use UpdatedAtTrait;
    use WorkspaceTrait;
    final public const int MAX_EXTENSION_SIZE = 20;
    final public const string GROUP_READ = 'file:r';
    final public const string GROUP_LIST = 'file:i';
    final public const string GROUP_METADATA = 'file:m';
    final public const string GROUP_WRITE = 'file:w';
    final public const string STORAGE_S3_MAIN = 's3_main';
    final public const string STORAGE_URL = 'url';

    final public const string ANALYSIS_SUCCESS = 'success';
    final public const string ANALYSIS_FAILED = 'failed';
    final public const string ANALYSIS_SKIPPED = 'skipped';
    final public const string ANALYSIS_BYPASSED = 'bypassed';

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

    #[ORM\Column(type: UuidType::NAME, nullable: true)]
    private ?string $docUniqueId = null;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: false)]
    private ?string $path = null;

    public ?string $localTmpPath = null;

    /**
     * Is path accessible from browser or worker.
     */
    #[ORM\Column(type: Types::BOOLEAN, nullable: false)]
    private bool $pathPublic = true;

    #[ORM\Column(type: Types::STRING, length: 150, nullable: false)]
    private ?string $storage = null;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $originalName = null;

    #[ORM\Column(type: Types::STRING, length: self::MAX_EXTENSION_SIZE, nullable: true)]
    private ?string $extension = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $alternateUrls = null;

    /**
     * The metadata read from the file. Never modified by the application.
     */
    #[ORM\OneToOne(targetEntity: FileMetadata::class, cascade: ['persist', 'remove'])]
    #[ORM\JoinColumn(nullable: true)]
    private ?FileMetadata $metadata = null;

    /**
     * The metadata set by the application, overriding the ones read from the file.
     */
    #[ORM\OneToOne(targetEntity: FileOverriddenMetadata::class, cascade: ['persist', 'remove'])]
    #[ORM\JoinColumn(nullable: true)]
    private ?FileOverriddenMetadata $overriddenMetadata = null;

    /**
     * Detailed analysis result, loaded on demand only (see FileAnalysis).
     * Null when the file was never analyzed or when no analysis was needed.
     */
    #[ORM\OneToOne(targetEntity: FileAnalysis::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\JoinColumn(nullable: true)]
    private ?FileAnalysis $analysis = null;

    /**
     * When the file was last analyzed (or marked as not needing analysis). Null while pending.
     */
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $analyzedAt = null;

    /**
     * Outcome of the analysis, denormalized here so that the file can be
     * displayed (URL, player) without loading the analysis. Null while pending.
     */
    #[ORM\Column(type: Types::BOOLEAN, nullable: true)]
    private ?bool $accepted = null;

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

    public function getDocUniqueId(): ?string
    {
        return $this->docUniqueId;
    }

    public function setDocUniqueId(?string $docUniqueId): void
    {
        $this->docUniqueId = $docUniqueId;
    }

    public function getFileName(): string
    {
        return $this->originalName ?? sprintf('%s%s', $this->getId(), $this->getExtensionWithDot());
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
        if (null !== $extension) {
            if (strlen($extension) > self::MAX_EXTENSION_SIZE) {
                throw new \InvalidArgumentException('Invalid extension (too long)');
            }
        }

        $this->extension = $extension;
    }

    /**
     * The metadata read from the file, with the application overrides applied on top.
     */
    public function getMetadata(?string $name = null): ?array
    {
        if (null !== $name) {
            return $this->getMetadataNameValues($name);
        }

        if (null === $this->metadata && null === $this->overriddenMetadata) {
            return null;
        }

        $resolved = $this->metadata?->getMetadata() ?? [];
        foreach ($this->overriddenMetadata?->getMetadata() ?? [] as $group => $tags) {
            foreach ($tags as $tag => $values) {
                $resolved[$group][$tag] = $values;
            }
        }

        return $resolved;
    }

    /**
     * The metadata read from the file, without any application override.
     */
    public function getReadMetadata(): ?array
    {
        return $this->metadata?->getMetadata();
    }

    /**
     * The metadata set by the application. These are the only ones written back into
     * rendition and export files.
     */
    public function getOverriddenMetadata(): array
    {
        return $this->overriddenMetadata?->getMetadata() ?? [];
    }

    /**
     * @return array<string, array> overridden values only, indexed by "Group:Tag"
     */
    public function getOverriddenMetadataValues(): array
    {
        return $this->overriddenMetadata?->getMetadataValues() ?? [];
    }

    public function getMetadataValues(): array
    {
        $values = $this->metadata?->getMetadataValues() ?? [];

        return array_merge($values, $this->getOverriddenMetadataValues());
    }

    public function setMetadata(?array $metadata): void
    {
        if (null === $metadata) {
            return;
        }

        if (empty($metadata)) {
            $this->metadata = null;
        } else {
            $this->metadata ??= new FileMetadata();
            $this->metadata->setMetadata($metadata);
        }
    }

    /**
     * Sets a metadata value the application owns. The metadata read from the file are left
     * untouched: the value goes to the overrides, and wins on read.
     */
    public function setMetadataValue(string $name, mixed $value, bool $append = false): void
    {
        $this->overriddenMetadata ??= new FileOverriddenMetadata();
        $this->overriddenMetadata->setMetadataValue(
            $name,
            $value,
            $append,
            // seed an append with the values currently read from the file
            $append ? ($this->metadata?->getMetadataNameValues($name) ?? []) : [],
        );
    }

    public function removeMetadataValue(string $name): void
    {
        $this->overriddenMetadata?->removeMetadataValue($name);
    }

    public function getMetadataNameValues(string $name): ?array
    {
        return $this->overriddenMetadata?->getMetadataNameValues($name)
            ?? $this->metadata?->getMetadataNameValues($name);
    }

    /**
     * Whether the application set metadata of its own on top of the ones read from the file.
     */
    public function metadataHasChanged(): bool
    {
        return false === $this->overriddenMetadata?->isEmpty();
    }

    public function getAnalysis(): ?FileAnalysis
    {
        return $this->analysis;
    }

    public function getAnalyzedAt(): ?\DateTimeImmutable
    {
        return $this->analyzedAt;
    }

    public function isAnalyzed(): bool
    {
        return null !== $this->analyzedAt;
    }

    /**
     * A file is accepted unless its analysis rejected it (a pending file is displayable).
     */
    public function isAccepted(): bool
    {
        return false !== $this->accepted;
    }

    /**
     * Records the outcome of an analysis, replacing any previous one.
     */
    public function setAnalysisResult(string $status, array $results = [], ?string $hash = null, ?string $message = null): FileAnalysis
    {
        $analysis = $this->analysis ?? new FileAnalysis($status);
        $analysis->setStatus($status);
        $analysis->setResults($results);
        $analysis->setHash($hash);
        $analysis->setMessage($message);
        $this->analysis = $analysis;

        $this->analyzedAt = new \DateTimeImmutable();
        $this->accepted = self::isAcceptedStatus($status);

        return $analysis;
    }

    /**
     * Changes the status of the existing analysis (e.g. after its results were rewritten).
     */
    public function setAnalysisStatus(string $status): void
    {
        if (null === $this->analysis) {
            $this->setAnalysisResult($status);

            return;
        }

        $this->analysis->setStatus($status);
        $this->accepted = self::isAcceptedStatus($status);
    }

    public function bypassAnalysis(): void
    {
        $this->setAnalysisStatus(self::ANALYSIS_BYPASSED);
    }

    public function resetAnalysis(): void
    {
        $this->analysis = null;
        $this->analyzedAt = null;
        $this->accepted = null;
    }

    public function setNoAnalysisNeeded(): void
    {
        if (!$this->isAnalyzed()) {
            $this->analyzedAt = new \DateTimeImmutable();
            $this->accepted = true;
        }
    }

    public static function isAcceptedStatus(string $status): bool
    {
        return in_array($status, [
            self::ANALYSIS_SUCCESS,
            self::ANALYSIS_SKIPPED,
            self::ANALYSIS_BYPASSED,
        ], true);
    }
}
