<?php

declare(strict_types=1);

namespace App\Entity\Core;

use Alchemy\CoreBundle\Entity\AbstractUuidEntity;
use Alchemy\CoreBundle\Entity\Traits\CreatedAtTrait;
use Alchemy\CoreBundle\Entity\Traits\UpdatedAtTrait;
use App\Repository\Core\AssetFaceRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * A face detected in an asset image by the face recognition integration.
 * Holds the bounding box, the recognition embedding and, once known, the identity of the person.
 */
#[ORM\Table(name: 'asset_face')]
#[ORM\Index(name: 'idx_asset_face_asset', columns: ['asset_id'])]
#[ORM\Index(name: 'idx_asset_face_identity', columns: ['identity_name'])]
#[ORM\Entity(repositoryClass: AssetFaceRepository::class)]
class AssetFace extends AbstractUuidEntity
{
    use CreatedAtTrait;
    use UpdatedAtTrait;

    /**
     * Identity set by a user: used as reference for recognizing other faces.
     */
    final public const string IDENTITY_ORIGIN_USER = 'user';

    /**
     * Identity automatically assigned by matching against user-identified faces.
     */
    final public const string IDENTITY_ORIGIN_AUTO = 'auto';

    #[ORM\ManyToOne(targetEntity: Asset::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Asset $asset = null;

    /**
     * Bounding box normalized to the image size: {x, y, w, h} in [0, 1], origin top-left.
     *
     * @var array{x: float, y: float, w: float, h: float}
     */
    #[ORM\Column(type: Types::JSON)]
    private array $box = ['x' => 0.0, 'y' => 0.0, 'w' => 0.0, 'h' => 0.0];

    /**
     * Detection order (largest faces first).
     */
    #[ORM\Column(type: Types::SMALLINT)]
    private int $position = 0;

    /**
     * Detection confidence in [0, 1].
     */
    #[ORM\Column(type: Types::FLOAT)]
    private float $confidence = 0.0;

    /**
     * @var float[] L2-normalized embedding
     */
    #[ORM\Column(type: Types::JSON)]
    private array $vector = [];

    #[ORM\Column(type: Types::STRING, length: 100)]
    private ?string $model = null;

    #[ORM\Column(type: Types::SMALLINT)]
    private int $dims = 0;

    #[ORM\Column(name: 'identity_name', type: Types::STRING, length: 255, nullable: true)]
    private ?string $identity = null;

    /**
     * 1.0 when set by a user, otherwise the similarity with the matched reference face.
     */
    #[ORM\Column(type: Types::FLOAT, nullable: true)]
    private ?float $identityConfidence = null;

    #[ORM\Column(type: Types::STRING, length: 20, nullable: true)]
    private ?string $identityOrigin = null;

    /**
     * The user-identified face this (automatic) identity was derived from.
     */
    #[ORM\ManyToOne(targetEntity: self::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?AssetFace $reference = null;

    /**
     * Extra details returned by the service (age, gender, landmarks...).
     */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $details = null;

    public function getAsset(): ?Asset
    {
        return $this->asset;
    }

    public function setAsset(?Asset $asset): void
    {
        $this->asset = $asset;
    }

    /**
     * @return array{x: float, y: float, w: float, h: float}
     */
    public function getBox(): array
    {
        return $this->box;
    }

    /**
     * @param array{x: float, y: float, w: float, h: float} $box
     */
    public function setBox(array $box): void
    {
        $this->box = [
            'x' => (float) $box['x'],
            'y' => (float) $box['y'],
            'w' => (float) $box['w'],
            'h' => (float) $box['h'],
        ];
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function setPosition(int $position): void
    {
        $this->position = $position;
    }

    public function getConfidence(): float
    {
        return $this->confidence;
    }

    public function setConfidence(float $confidence): void
    {
        $this->confidence = $confidence;
    }

    /**
     * @return float[]
     */
    public function getVector(): array
    {
        return $this->vector;
    }

    /**
     * @param float[] $vector
     */
    public function setVector(array $vector): void
    {
        $this->vector = $vector;
        $this->dims = count($vector);
    }

    public function getModel(): ?string
    {
        return $this->model;
    }

    public function setModel(?string $model): void
    {
        $this->model = $model;
    }

    public function getDims(): int
    {
        return $this->dims;
    }

    public function getIdentity(): ?string
    {
        return $this->identity;
    }

    public function hasIdentity(): bool
    {
        return null !== $this->identity;
    }

    public function isUserIdentified(): bool
    {
        return null !== $this->identity && self::IDENTITY_ORIGIN_USER === $this->identityOrigin;
    }

    public function setIdentity(?string $identity, ?string $origin, ?float $confidence, ?self $reference = null): void
    {
        $identity = null !== $identity ? trim($identity) : null;
        if ('' === $identity) {
            $identity = null;
        }

        $this->identity = $identity;
        $this->identityOrigin = null !== $identity ? $origin : null;
        $this->identityConfidence = null !== $identity ? $confidence : null;
        $this->reference = null !== $identity && self::IDENTITY_ORIGIN_AUTO === $origin ? $reference : null;
    }

    public function clearIdentity(): void
    {
        $this->setIdentity(null, null, null);
    }

    public function getReference(): ?self
    {
        return $this->reference;
    }

    public function setReference(?self $reference): void
    {
        $this->reference = $reference;
    }

    public function getIdentityConfidence(): ?float
    {
        return $this->identityConfidence;
    }

    public function getIdentityOrigin(): ?string
    {
        return $this->identityOrigin;
    }

    public function getDetails(): ?array
    {
        return $this->details;
    }

    public function setDetails(?array $details): void
    {
        $this->details = $details;
    }

    public function __toString(): string
    {
        return $this->identity ?? $this->getId();
    }
}
