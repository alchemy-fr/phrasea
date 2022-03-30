<?php

declare(strict_types=1);

namespace App\Entity\Core;

use App\Entity\AbstractUuidEntity;
use App\Entity\SearchDeleteDependencyInterface;
use App\Entity\Traits\CreatedAtTrait;
use App\Entity\Traits\UpdatedAtTrait;
use DateTimeInterface;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="App\Repository\Core\AttributeRepository")
 */
class Attribute extends AbstractUuidEntity implements SearchDeleteDependencyInterface
{
    use CreatedAtTrait;
    use UpdatedAtTrait;
    public const ORIGIN_MACHINE = 0;
    public const ORIGIN_HUMAN = 1;
    public const ORIGIN_FALLBACK = 2;

    public const ORIGIN_LABELS = [
        self::ORIGIN_MACHINE => 'machine',
        self::ORIGIN_HUMAN => 'human',
        self::ORIGIN_FALLBACK => 'fallback',
    ];

    const STATUS_VALID = 0;
    const STATUS_REVIEW_PENDING = 1;
    const STATUS_DECLINED = 2;

    public const STATUS_LABELS = [
        self::STATUS_VALID => 'valid',
        self::STATUS_REVIEW_PENDING => 'review_pending',
        self::STATUS_DECLINED => 'declined',
    ];

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Core\Asset", inversedBy="attributes")
     * @ORM\JoinColumn(nullable=false)
     */
    private ?Asset $asset = null;

    /**
     * @ORM\Column(type="string", length=10, nullable=true)
     */
    private ?string $locale = null;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Core\AttributeDefinition", inversedBy="attributes")
     * @ORM\JoinColumn(nullable=false)
     */
    protected ?AttributeDefinition $definition = null;

    /**
     * Unique ID to group translations of the same attribute.
     *
     * @ORM\Column(type="uuid", nullable=true)
     */
    private ?string $translationId = null;

    /**
     * @ORM\Column(type="text", nullable=false)
     */
    private ?string $value = null;

    /**
     * Resolved by \App\Api\DataTransformer\AssetOutputDataTransformer.
     */
    private ?array $values = null;

    /**
     * Dynamically resolved.
     */
    private ?string $highlight = null;

    /**
     * Dynamically resolved.
     */
    private ?array $highlights = null;

    /**
     * @ORM\Column(type="smallint", nullable=false)
     */
    private int $origin;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private ?string $originVendor = null;

    /**
     * @ORM\Column(type="uuid", nullable=true)
     */
    private ?string $originUserId = null;

    /**
     * Could include vendor version, AI parameters, etc.
     *
     * @ORM\Column(type="text", nullable=true)
     */
    private ?string $originVendorContext = null;

    /**
     * @ORM\Column(type="text", nullable=true)
     */
    private ?string $coordinates = null;

    /**
     * @ORM\Column(type="smallint", nullable=true)
     */
    private int $status = self::STATUS_VALID;

    /**
     * @ORM\Column(type="float", nullable=false)
     */
    private float $confidence = 1.0;

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

    public function getValue(): ?string
    {
        return $this->value;
    }

    public function setValue(?string $value): void
    {
        $this->value = $value;
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

    public function getLocale(): ?string
    {
        return $this->locale;
    }

    public function hasLocale(): bool
    {
        return null !== $this->locale;
    }

    public function setLocale(?string $locale): void
    {
        $this->locale = $locale;
    }

    public function getSearchDeleteDependencies(): array
    {
        return [
            $this->getAsset(),
        ];
    }

    public function getValues(): ?array
    {
        return $this->values;
    }

    public function setValues(?array $values): void
    {
        $this->values = $values;
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

    public function setCreatedAt(DateTimeInterface $createdAt): void
    {
        $this->createdAt = $createdAt;
    }

    public function setUpdatedAt(DateTimeInterface $updatedAt): void
    {
        $this->updatedAt = $updatedAt;
    }
}
