<?php

declare(strict_types=1);

namespace App\Entity\Core;

use Alchemy\CoreBundle\Entity\AbstractUuidEntity;
use Alchemy\CoreBundle\Entity\Traits\CreatedAtTrait;
use Alchemy\CoreBundle\Entity\Traits\UpdatedAtTrait;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use App\Api\Model\Input\AssetRenditionInput;
use App\Api\Model\Output\AssetRenditionOutput;
use App\Api\Processor\DeleteAssetRenditionProcessor;
use App\Api\Provider\RenditionCollectionProvider;
use App\Repository\Core\AssetRenditionRepository;
use App\Security\Voter\AbstractVoter;
use App\Validator as CustomAssert;
use App\Validator\SameWorkspaceConstraint;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

#[ApiResource(
    shortName: 'rendition',
    operations: [
        new Get(security: 'is_granted("'.AbstractVoter::READ.'", object)'),
        new Delete(
            security: 'is_granted("'.AbstractVoter::DELETE.'", object)',
            processor: DeleteAssetRenditionProcessor::class,
        ),
        new Put(security: 'is_granted("'.AbstractVoter::EDIT.'", object)'),
        new Patch(security: 'is_granted("'.AbstractVoter::EDIT.'", object)'),
        new GetCollection(
            order: ['definition.position' => 'ASC'],
        ),
        new Post(
            openapiContext: [
                'requestBody' => [
                    'content' => [
                        'application/json' => [
                            'examples' => [
                                'Dynamic rendition (built from an inline specification)' => [
                                    'value' => [
                                        'assetId' => 'f30e1e4d-fef6-4870-9106-87167083e0f6',
                                        'name' => 'my-custom-crop',
                                        'buildDefinition' => '{"image": {"transformations": [{"module": "imagine", "options": {"filters": {"crop": {"start": [10, 10], "size": [300, 200]}}}}]}}',
                                        'writeMetadata' => true,
                                        'sourceRenditionId' => 'c30e1e4d-fef6-4870-9106-87167083e0f6',
                                    ],
                                ],
                                'Multipart upload' => [
                                    'value' => [
                                        'assetId' => 'f30e1e4d-fef6-4870-9106-87167083e0f6',
                                        'name' => 'preview',
                                        'multipart' => [
                                            'uploadId' => '123-456',
                                            'parts' => [
                                                [
                                                    'ETag' => '812d692260ab94dd85a5aa7a6caef68d',
                                                    'PartNumber' => 1,
                                                ],
                                                [
                                                    'ETag' => '4dd85a5aa7a6caef68d812d692260ab9',
                                                    'PartNumber' => 2,
                                                ],
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                            'schema' => [
                                'type' => 'object',
                                'properties' => [
                                    'multipart' => [
                                        'type' => 'object',
                                        'properties' => [
                                            'uploadId' => ['type' => 'string'],
                                            'parts' => [
                                                'type' => 'array',
                                                'items' => [
                                                    'type' => 'object',
                                                    'properties' => [
                                                        'ETag' => ['type' => 'string'],
                                                        'PartNumber' => ['type' => 'integer'],
                                                    ],
                                                ],
                                            ],
                                        ],
                                    ],
                                    'assetId' => ['type' => 'string'],
                                    'name' => [
                                        'type' => 'string',
                                        'description' => 'The definition name, or the custom rendition name when "buildDefinition" is provided',
                                    ],
                                    'buildDefinition' => [
                                        'type' => 'string',
                                        'description' => 'Inline build specification (same YAML format as a rendition definition). The rendition is built asynchronously from it.',
                                    ],
                                    'writeMetadata' => [
                                        'type' => 'boolean',
                                        'description' => 'Write the asset attribute metadata into the built file (with "buildDefinition" only)',
                                    ],
                                    'sourceRenditionId' => [
                                        'type' => 'string',
                                        'description' => 'Build from this rendition\'s file instead of the asset source file (with "buildDefinition" only)',
                                    ],
                                ],
                            ],
                        ],
                        'multipart/form-data' => [
                            'schema' => [
                                'type' => 'object',
                                'properties' => [
                                    'file' => [
                                        'type' => 'string',
                                        'format' => 'binary',
                                    ],
                                    'assetId' => ['type' => 'string'],
                                    'name' => [
                                        'type' => 'string',
                                        'description' => 'The definition name',
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            securityPostDenormalize: 'is_granted("CREATE", object)',
            validationContext: [
                'groups' => ['Default'],
            ],
        ),
    ],
    normalizationContext: [
        'groups' => [AssetRendition::GROUP_LIST],
    ],
    input: AssetRenditionInput::class,
    output: AssetRenditionOutput::class,
    provider: RenditionCollectionProvider::class,
)]
#[ORM\Table]
#[ORM\UniqueConstraint(name: 'uniq_representation', columns: ['definition_id', 'asset_id'])]
#[ORM\UniqueConstraint(name: 'uniq_dynamic_rendition_name', columns: ['asset_id', 'name'], options: ['where' => '(definition_id IS NULL)'])]
#[ORM\Entity(repositoryClass: AssetRenditionRepository::class)]
#[SameWorkspaceConstraint(
    properties: ['asset.workspace', 'file.workspace', 'definition.workspace']
)]
class AssetRendition extends AbstractUuidEntity
{
    use CreatedAtTrait;
    use UpdatedAtTrait;
    final public const string GROUP_READ = 'assetrend:read';
    final public const string GROUP_LIST = 'assetrend:index';

    final public const string OPTION_WRITE_METADATA = 'write_metadata';
    final public const string OPTION_SOURCE_RENDITION_ID = 'source_rendition_id';

    #[ORM\ManyToOne(targetEntity: RenditionDefinition::class, inversedBy: 'renditions')]
    #[ORM\JoinColumn(nullable: true)]
    private ?RenditionDefinition $definition = null;

    /**
     * Custom name for dynamic renditions (built from an inline build definition, without a RenditionDefinition).
     */
    #[ORM\Column(type: Types::STRING, length: 100, nullable: true)]
    private ?string $name = null;

    /**
     * Inline build specification (same YAML format as RenditionDefinition::$definition) for dynamic renditions.
     */
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[CustomAssert\ValidRenditionDefinitionConstraint]
    private ?string $buildDefinition = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $buildOptions = null;

    #[ORM\ManyToOne(targetEntity: Asset::class, inversedBy: 'renditions')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Asset $asset = null;

    #[ORM\ManyToOne(targetEntity: File::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?File $file = null;

    /**
     * Homothetic and same format as source.
     */
    #[ORM\Column(type: Types::BOOLEAN, nullable: true)]
    private ?bool $projection = null;

    /**
     * Rendition cannot be substituted.
     */
    #[ORM\Column(type: Types::BOOLEAN)]
    private bool $locked = false;

    #[ORM\Column(type: Types::BOOLEAN)]
    private bool $substituted = false;

    /**
     * Hash based on the build process.
     */
    #[ORM\Column(type: Types::STRING, length: 32, nullable: true)]
    private ?string $buildHash = null;

    /**
     * Hash based on the build process.
     */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $moduleHashes = null;

    public function getAsset(): Asset
    {
        return $this->asset;
    }

    public function setAsset(Asset $asset): void
    {
        $this->asset = $asset;
        $asset->getRenditions()->add($this);
    }

    public function getFile(): ?File
    {
        return $this->file;
    }

    public function setFile(?File $file): void
    {
        $this->file = $file;
    }

    public function getDefinition(): ?RenditionDefinition
    {
        return $this->definition;
    }

    public function setDefinition(?RenditionDefinition $definition): void
    {
        $this->definition = $definition;
    }

    public function getName(): string
    {
        return $this->name ?? $this->definition->getName();
    }

    public function setName(?string $name): void
    {
        $this->name = $name;
    }

    public function isDynamic(): bool
    {
        return null === $this->definition;
    }

    public function getBuildDefinition(): ?string
    {
        return $this->buildDefinition;
    }

    public function setBuildDefinition(?string $buildDefinition): void
    {
        $this->buildDefinition = $buildDefinition;
    }

    public function getBuildOptions(): ?array
    {
        return $this->buildOptions;
    }

    public function setBuildOptions(?array $buildOptions): void
    {
        $this->buildOptions = $buildOptions;
    }

    public function isWriteMetadata(): bool
    {
        return $this->buildOptions[self::OPTION_WRITE_METADATA] ?? false;
    }

    public function getSourceRenditionId(): ?string
    {
        return $this->buildOptions[self::OPTION_SOURCE_RENDITION_ID] ?? null;
    }

    #[Assert\Callback]
    public function validateDynamicInvariant(ExecutionContextInterface $context): void
    {
        if (null !== $this->definition) {
            return;
        }

        if (null === $this->name || '' === $this->name) {
            $context->buildViolation('A dynamic rendition (without definition) requires a name.')
                ->atPath('name')
                ->addViolation();
        }
        if (null === $this->buildDefinition || '' === $this->buildDefinition) {
            $context->buildViolation('A dynamic rendition (without definition) requires a build definition.')
                ->atPath('buildDefinition')
                ->addViolation();
        }
    }

    public function isReady(): bool
    {
        return null !== $this->file;
    }

    public function getProjection(): ?bool
    {
        return $this->projection;
    }

    public function setProjection(?bool $projection): void
    {
        $this->projection = $projection;
    }

    public function getBuildHash(): ?string
    {
        return $this->buildHash;
    }

    public function setBuildHash(?string $buildHash): void
    {
        $this->buildHash = $buildHash;
    }

    public function getModuleHashes(): ?array
    {
        return $this->moduleHashes;
    }

    public function setModuleHashes(?array $moduleHashes): void
    {
        $this->moduleHashes = $moduleHashes;
    }

    public function isLocked(): bool
    {
        return $this->locked;
    }

    public function setLocked(bool $locked): void
    {
        $this->locked = $locked;
    }

    public function isSubstituted(): bool
    {
        return $this->substituted;
    }

    public function setSubstituted(bool $substituted): void
    {
        $this->substituted = $substituted;
    }
}
