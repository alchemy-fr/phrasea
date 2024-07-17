<?php

declare(strict_types=1);

namespace App\Entity\Core;

use Alchemy\ESBundle\Indexer\ESIndexableDeleteDependencyInterface;
use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use App\Api\Model\Input\Attribute\AttributeBatchUpdateInput;
use App\Api\Model\Input\Attribute\AttributeInput;
use App\Api\Model\Output\AttributeOutput;
use App\Api\Processor\BatchAttributeUpdateProcessor;
use App\Api\Provider\AttributeCollectionProvider;
use App\Entity\Traits\AssetAnnotationsTrait;
use App\Repository\Core\AttributeRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Doctrine\UuidType;

#[ApiResource(
    shortName: 'attribute',
    operations: [
        new Get(),
        new Delete(security: 'is_granted("DELETE", object)'),
        new Put(security: 'is_granted("EDIT", object)'),
        new Patch(security: 'is_granted("EDIT", object)'),
        new GetCollection(),
        new Post(
            securityPostDenormalize: 'is_granted("CREATE", object)'
        ),
        new Post(
            uriTemplate: '/attributes/batch-update',
            status: 200,
            input: AttributeBatchUpdateInput::class,
            name: 'post_batch',
            processor: BatchAttributeUpdateProcessor::class,
        ),
    ],
    normalizationContext: [
        'groups' => [
            Attribute::GROUP_LIST,
        ],
    ],
    input: AttributeInput::class,
    output: AttributeOutput::class,
    provider: AttributeCollectionProvider::class,
)]

#[ORM\Entity(repositoryClass: AttributeRepository::class)]
#[ApiFilter(filterClass: SearchFilter::class, properties: ['asset' => 'exact'])]
class Attribute extends AbstractBaseAttribute implements ESIndexableDeleteDependencyInterface
{
    use AssetAnnotationsTrait;

    final public const GROUP_READ = 'attr:read';
    final public const GROUP_LIST = 'attr:index';

    final public const ORIGIN_MACHINE = 0;
    final public const ORIGIN_HUMAN = 1;
    final public const ORIGIN_FALLBACK = 2;
    final public const ORIGIN_INITIAL = 3;

    final public const ORIGIN_LABELS = [
        self::ORIGIN_MACHINE => 'machine',
        self::ORIGIN_HUMAN => 'human',
        self::ORIGIN_FALLBACK => 'fallback',
        self::ORIGIN_INITIAL => 'initial',
    ];

    final public const STATUS_VALID = 0;
    final public const STATUS_REVIEW_PENDING = 1;
    final public const STATUS_DECLINED = 2;

    final public const STATUS_LABELS = [
        self::STATUS_VALID => 'valid',
        self::STATUS_REVIEW_PENDING => 'review_pending',
        self::STATUS_DECLINED => 'declined',
    ];

    #[ORM\ManyToOne(targetEntity: Asset::class, inversedBy: 'attributes')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Asset $asset = null;

    #[ORM\Column(type: Types::BOOLEAN, nullable: false)]
    private bool $locked = false;

    #[ORM\ManyToOne(targetEntity: AttributeDefinition::class, inversedBy: 'attributes')]
    #[ORM\JoinColumn(nullable: false)]
    protected ?AttributeDefinition $definition = null;

    /**
     * Dynamically resolved.
     */
    private ?string $highlight = null;

    #[ORM\Column(type: Types::SMALLINT, nullable: false)]
    private ?int $origin = null;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $originVendor = null;

    #[ORM\Column(type: UuidType::NAME, nullable: true)]
    private ?string $originUserId = null;

    /**
     * Could include vendor version, AI parameters, etc.
     */
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $originVendorContext = null;

    #[ORM\Column(type: Types::SMALLINT, nullable: true)]
    private int $status = self::STATUS_VALID;

    #[ORM\Column(type: Types::FLOAT, nullable: false)]
    private float $confidence = 1.0;

    public ?AttributeBatchUpdateInput $batchUpdate = null;

    public function getAsset(): ?Asset
    {
        return $this->asset;
    }

    public function setAsset(?Asset $asset): void
    {
        $this->asset = $asset;
    }

    public function getDefinition(): ?AttributeDefinition
    {
        return $this->definition;
    }

    public function setDefinition(?AttributeDefinition $definition): void
    {
        $this->definition = $definition;
    }

    /**
     * Used by ES.
     */
    public function getDefinitionId(): string
    {
        return $this->definition->getId();
    }

    public function hasOrigin(): bool
    {
        return null !== $this->origin;
    }

    public function getOrigin(): int
    {
        return $this->origin;
    }

    public function setOrigin(int $origin): void
    {
        $this->origin = $origin;
    }

    public function getOriginLabel(): string
    {
        return self::ORIGIN_LABELS[$this->origin];
    }

    public function getOriginVendor(): ?string
    {
        return $this->originVendor;
    }

    public function setOriginVendor(?string $originVendor): void
    {
        $this->originVendor = $originVendor;
    }

    public function getOriginUserId(): ?string
    {
        return $this->originUserId;
    }

    public function setOriginUserId(?string $originUserId): void
    {
        $this->originUserId = $originUserId;
    }

    public function getOriginVendorContext(): ?string
    {
        return $this->originVendorContext;
    }

    public function setOriginVendorContext(?string $originVendorContext): void
    {
        $this->originVendorContext = $originVendorContext;
    }

    public function getStatus(): int
    {
        return $this->status;
    }

    public function setStatus(int $status): void
    {
        $this->status = $status;
    }

    public function getStatusLabel(): string
    {
        return self::STATUS_LABELS[$this->status];
    }

    public function getConfidence(): float
    {
        return $this->confidence;
    }

    public function setConfidence(float $confidence): void
    {
        $this->confidence = $confidence;
    }

    public function getIndexableDeleteDependencies(): array
    {
        return [
            $this->getAsset(),
        ];
    }

    public function getHighlight(): ?string
    {
        return $this->highlight;
    }

    public function setHighlight(?string $highlight): void
    {
        $this->highlight = $highlight;
    }

    public function isLocked(): bool
    {
        return $this->locked;
    }

    public function setLocked(bool $locked): void
    {
        $this->locked = $locked;
    }
}
