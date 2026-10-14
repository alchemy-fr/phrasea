<?php

declare(strict_types=1);

namespace App\Entity\Core;

use Alchemy\CoreBundle\Entity\AbstractUuidEntity;
use Alchemy\CoreBundle\Entity\Traits\CreatedAtTrait;
use Alchemy\CoreBundle\Entity\Traits\UpdatedAtTrait;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\QueryParameter;
use App\Api\Model\Input\AssetFaceInput;
use App\Api\Model\Output\AssetFaceOutput;
use App\Api\Processor\AssetFaceIdentifyProcessor;
use App\Api\Provider\AssetFaceCollectionProvider;
use App\Api\Provider\AssetFaceIdentityProvider;
use App\Repository\Core\AssetFaceRepository;
use App\Security\Voter\AbstractVoter;
use App\Security\Voter\AssetFaceVoter;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * A face detected in an asset image by the face recognition integration.
 * Holds the bounding box, the recognition embedding and, once known, the identity of the person.
 */
#[ApiResource(
    shortName: 'asset-face',
    operations: [
        new GetCollection(
            uriTemplate: '/asset-faces/identities',
            name: 'identities',
            description: 'The persons named in the workspaces, with their number of faces, and the number of unnamed faces',
            security: 'is_granted("'.AssetFaceVoter::EDIT_FACES.'")',
            provider: AssetFaceIdentityProvider::class,
            parameters: [
                'workspace' => new QueryParameter(
                    schema: ['type' => 'string'],
                    description: 'Workspace ID or IRI (every readable workspace without it)',
                ),
                'workspace[]' => new QueryParameter(property: 'workspace', openApi: false),
                'query' => new QueryParameter(
                    schema: ['type' => 'string'],
                    description: 'Text the names must contain',
                    castToArray: false,
                ),
            ],
        ),
        new GetCollection(
            security: 'is_granted("'.AssetFaceVoter::EDIT_FACES.'")',
            provider: AssetFaceCollectionProvider::class,
            parameters: [
                'workspace' => new QueryParameter(
                    schema: ['type' => 'string'],
                    description: 'Workspace ID or IRI (every readable workspace without it)',
                ),
                'workspace[]' => new QueryParameter(property: 'workspace', openApi: false),
                'asset' => new QueryParameter(
                    schema: ['type' => 'string'],
                    description: 'Asset ID or IRI',
                    castToArray: false,
                ),
                'identity' => new QueryParameter(
                    schema: ['type' => 'string'],
                    description: 'Faces of this person (exact name)',
                    castToArray: false,
                ),
                'identified' => new QueryParameter(
                    schema: ['type' => 'boolean'],
                    description: 'Named (true) or unnamed (false) faces only',
                    castToArray: false,
                ),
                'origin' => new QueryParameter(
                    schema: ['type' => 'string', 'enum' => [AssetFace::IDENTITY_ORIGIN_USER, AssetFace::IDENTITY_ORIGIN_AUTO]],
                    description: 'Faces named by a user, or recognized automatically',
                    castToArray: false,
                ),
                'query' => new QueryParameter(
                    schema: ['type' => 'string'],
                    description: 'Text the name must contain',
                    castToArray: false,
                ),
                'limit' => new QueryParameter(
                    schema: ['type' => 'integer'],
                    description: 'Page size (max 100)',
                    castToArray: false,
                ),
                'page' => new QueryParameter(
                    schema: ['type' => 'integer'],
                    castToArray: false,
                ),
            ],
        ),
        new Get(
            security: 'is_granted("'.AbstractVoter::READ.'", object)',
        ),
        new Post(
            uriTemplate: '/asset-faces/{id}/identity',
            status: 200,
            description: 'Names the face (or removes its name with a null identity); the name is applied to the similar faces of the workspace when the integration propagates identities',
            // "object" is the input DTO here: the EDIT check on the face itself is done by the processor
            security: 'is_granted("'.AssetFaceVoter::EDIT_FACES.'")',
            input: AssetFaceInput::class,
            name: 'identify',
            processor: AssetFaceIdentifyProcessor::class,
            throwOnNotFound: true,
        ),
    ],
    normalizationContext: ['groups' => [
        '_',
        AssetFace::GROUP_LIST,
        Asset::GROUP_LIST,
    ]],
    output: AssetFaceOutput::class,
)]
#[ORM\Table(name: 'asset_face')]
#[ORM\Index(name: 'idx_asset_face_asset', columns: ['asset_id'])]
#[ORM\Index(name: 'idx_asset_face_identity', columns: ['identity_name'])]
#[ORM\Entity(repositoryClass: AssetFaceRepository::class)]
class AssetFace extends AbstractUuidEntity
{
    use CreatedAtTrait;
    use UpdatedAtTrait;

    final public const string GROUP_LIST = 'asset-face:i';

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
