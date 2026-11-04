<?php

declare(strict_types=1);

namespace App\Entity\Core;

use Alchemy\AuthBundle\Security\JwtUser;
use Alchemy\CoreBundle\Entity\AbstractUuidEntity;
use Alchemy\CoreBundle\Entity\Traits\CreatedAtTrait;
use Alchemy\CoreBundle\Entity\Traits\UpdatedAtTrait;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\QueryParameter;
use App\Api\Model\Input\AssetImportInput;
use App\Api\Model\Output\AssetImportOutput;
use App\Api\Processor\CreateAssetImportProcessor;
use App\Api\Processor\DeleteAssetImportProcessor;
use App\Api\Provider\AssetImportCollectionProvider;
use App\Entity\Integration\WorkspaceIntegration;
use App\Entity\Traits\OwnerIdTrait;
use App\Model\AssetImportStatusEnum;
use App\Security\Voter\AbstractVoter;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * The import of the assets described by a JSON or NDJSON instructions file.
 */
#[ApiResource(
    shortName: 'asset-import',
    operations: [
        new Get(
            security: 'is_granted("'.AbstractVoter::READ.'", object)',
        ),
        new GetCollection(
            parameters: [
                'workspace' => new QueryParameter(
                    schema: ['type' => 'string'],
                    description: 'Workspace ID or IRI (mandatory)',
                    required: true,
                    castToArray: false,
                ),
                'limit' => new QueryParameter(
                    schema: ['type' => 'integer'],
                    description: 'Page size (default 30, max 100)',
                    castToArray: false,
                ),
                'page' => new QueryParameter(
                    schema: ['type' => 'integer'],
                    castToArray: false,
                ),
            ],
            provider: AssetImportCollectionProvider::class,
        ),
        new Post(
            input: AssetImportInput::class,
            processor: CreateAssetImportProcessor::class,
        ),
        new Delete(
            security: 'is_granted("'.AbstractVoter::DELETE.'", object)',
            processor: DeleteAssetImportProcessor::class,
        ),
    ],
    normalizationContext: [
        'groups' => ['_', 'dates', self::GROUP_READ],
    ],
    output: AssetImportOutput::class,
    security: 'is_granted("'.JwtUser::IS_AUTHENTICATED_FULLY.'")',
)]
#[ORM\Entity]
#[ORM\Index(columns: ['workspace_id', 'created_at'], name: 'asset_import_ws_created_idx')]
class AssetImport extends AbstractUuidEntity
{
    use OwnerIdTrait;
    use CreatedAtTrait;
    use UpdatedAtTrait;

    final public const string GROUP_READ = 'asset-import:r';

    final public const string FORMAT_JSON = 'json';
    final public const string FORMAT_NDJSON = 'ndjson';
    final public const array FORMATS = [self::FORMAT_JSON, self::FORMAT_NDJSON];

    #[ORM\ManyToOne(targetEntity: Workspace::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Workspace $workspace = null;

    /**
     * The default destination of the entries.
     */
    #[ORM\ManyToOne(targetEntity: Collection::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Collection $collection = null;

    /**
     * The integration whose webhook received the instructions.
     */
    #[ORM\ManyToOne(targetEntity: WorkspaceIntegration::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?WorkspaceIntegration $integration = null;

    #[ORM\Column(type: Types::STRING, length: 10, nullable: false)]
    private string $format = self::FORMAT_NDJSON;

    /**
     * The default privacy of the entries.
     */
    #[ORM\Column(type: Types::SMALLINT, nullable: true)]
    private ?int $privacy = null;

    /**
     * The storage path of the instructions file.
     */
    #[ORM\Column(type: Types::STRING, length: 255, nullable: false)]
    private ?string $sourcePath = null;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $originalName = null;

    #[ORM\Column(type: Types::SMALLINT, nullable: false, enumType: AssetImportStatusEnum::class)]
    private AssetImportStatusEnum $status = AssetImportStatusEnum::Pending;

    /**
     * The number of entries, known once the whole file is read.
     */
    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    private ?int $total = null;

    #[ORM\Column(type: Types::INTEGER, nullable: false, options: ['default' => 0])]
    private int $created = 0;

    #[ORM\Column(type: Types::INTEGER, nullable: false, options: ['default' => 0])]
    private int $skipped = 0;

    #[ORM\Column(type: Types::INTEGER, nullable: false, options: ['default' => 0])]
    private int $failed = 0;

    /**
     * The fatal error that stopped the import (e.g. an unreadable file).
     */
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $error = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $endedAt = null;

    public function getWorkspace(): ?Workspace
    {
        return $this->workspace;
    }

    public function getWorkspaceId(): ?string
    {
        return $this->workspace?->getId();
    }

    public function setWorkspace(Workspace $workspace): void
    {
        $this->workspace = $workspace;
    }

    public function getCollection(): ?Collection
    {
        return $this->collection;
    }

    public function setCollection(?Collection $collection): void
    {
        $this->collection = $collection;
    }

    public function getIntegration(): ?WorkspaceIntegration
    {
        return $this->integration;
    }

    public function setIntegration(?WorkspaceIntegration $integration): void
    {
        $this->integration = $integration;
    }

    public function getFormat(): string
    {
        return $this->format;
    }

    public function setFormat(string $format): void
    {
        if (!in_array($format, self::FORMATS, true)) {
            throw new \InvalidArgumentException(sprintf('Invalid import format "%s"', $format));
        }

        $this->format = $format;
    }

    public function getPrivacy(): ?int
    {
        return $this->privacy;
    }

    public function setPrivacy(?int $privacy): void
    {
        $this->privacy = $privacy;
    }

    public function getSourcePath(): ?string
    {
        return $this->sourcePath;
    }

    public function setSourcePath(string $sourcePath): void
    {
        $this->sourcePath = $sourcePath;
    }

    public function getOriginalName(): ?string
    {
        return $this->originalName;
    }

    public function setOriginalName(?string $originalName): void
    {
        $this->originalName = $originalName;
    }

    public function getStatus(): AssetImportStatusEnum
    {
        return $this->status;
    }

    public function setStatus(AssetImportStatusEnum $status): void
    {
        $this->status = $status;
    }

    public function getTotal(): ?int
    {
        return $this->total;
    }

    public function getCreated(): int
    {
        return $this->created;
    }

    public function getSkipped(): int
    {
        return $this->skipped;
    }

    public function getFailed(): int
    {
        return $this->failed;
    }

    public function getError(): ?string
    {
        return $this->error;
    }

    public function getEndedAt(): ?\DateTimeImmutable
    {
        return $this->endedAt;
    }
}
