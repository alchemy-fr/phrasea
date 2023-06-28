<?php

declare(strict_types=1);

namespace App\Entity\Core;

use ApiPlatform\Core\Annotation\ApiFilter;
use ApiPlatform\Core\Bridge\Doctrine\Orm\Filter\SearchFilter;
use App\Api\Model\Input\Attribute\AttributeBatchUpdateInput;
use App\Entity\SearchDeleteDependencyInterface;
use App\Repository\Core\AttributeRepository;
use Doctrine\Common\Collections\Collection as DoctrineCollection;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ApiFilter(filterClass=SearchFilter::class, properties={"asset"="exact"})
 */
#[ORM\Entity(repositoryClass: AttributeRepository::class)]
class Attribute extends AbstractBaseAttribute implements SearchDeleteDependencyInterface
{
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

    #[ORM\Column(type: 'boolean', nullable: false)]
    private bool $locked = false;

    #[ORM\ManyToOne(targetEntity: AttributeDefinition::class, inversedBy: 'attributes')]
    #[ORM\JoinColumn(nullable: false)]
    protected ?AttributeDefinition $definition = null;

    /**
     * Unique ID to group translations of the same attribute.
     */
    #[ORM\Column(type: 'uuid', nullable: true)]
    private ?string $translationId = null;

    /**
     * Unique ID to group translations of the same attribute.
     */
    #[ORM\ManyToOne(targetEntity: Attribute::class, inversedBy: 'translations')]
    #[ORM\JoinColumn(nullable: true)]
    private ?self $translationOrigin = null;

    /**
     * Hashed value of the original translated string.
     */
    #[ORM\Column(type: 'string', length: 32, nullable: true)]
    private ?string $translationOriginHash = null;

    #[ORM\OneToMany(targetEntity: Attribute::class, mappedBy: 'translationOrigin', cascade: ['remove'])]
    #[ORM\JoinColumn(nullable: true)]
    private ?DoctrineCollection $translations = null;

    /**
     * Dynamically resolved.
     */
    private ?string $highlight = null;

    /**
     * Dynamically resolved.
     */
    private ?array $highlights = null;

    #[ORM\Column(type: 'smallint', nullable: false)]
    private ?int $origin = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $originVendor = null;

    #[ORM\Column(type: 'uuid', nullable: true)]
    private ?string $originUserId = null;

    /**
     * Could include vendor version, AI parameters, etc.
     */
    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $originVendorContext = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $coordinates = null;

    #[ORM\Column(type: 'smallint', nullable: true)]
    private int $status = self::STATUS_VALID;

    #[ORM\Column(type: 'float', nullable: false)]
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

    public function getTranslationId(): ?string
    {
        return $this->translationId;
    }

    public function setTranslationId(?string $translationId): void
    {
        $this->translationId = $translationId;
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

    public function getCoordinates(): ?string
    {
        return $this->coordinates;
    }

    public function setCoordinates(?string $coordinates): void
    {
        $this->coordinates = $coordinates;
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

    public function getSearchDeleteDependencies(): array
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

    public function getHighlights(): ?array
    {
        return $this->highlights;
    }

    public function setHighlights(?array $highlights): void
    {
        $this->highlights = $highlights;
    }

    public function getTranslationOrigin(): ?Attribute
    {
        return $this->translationOrigin;
    }

    public function getTranslationOriginHash(): ?string
    {
        return $this->translationOriginHash;
    }

    public function setTranslationOriginHash(?string $translationOriginHash): void
    {
        $this->translationOriginHash = $translationOriginHash;
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
