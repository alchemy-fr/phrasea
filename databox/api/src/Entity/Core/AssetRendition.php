<?php

declare(strict_types=1);

namespace App\Entity\Core;

use Alchemy\CoreBundle\Entity\AbstractUuidEntity;
use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use App\Api\Model\Input\RenditionInput;
use App\Api\Model\Output\AssetRenditionOutput;
use App\Api\Provider\RenditionCollectionProvider;
use App\Entity\Traits\CreatedAtTrait;
use App\Entity\Traits\UpdatedAtTrait;
use App\Repository\Core\AssetRenditionRepository;
use App\Security\Voter\AbstractVoter;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

#[ApiResource(
    shortName: 'rendition',
    operations: [
        new Get(security: 'is_granted("'.AbstractVoter::READ.'", object)'),
        new Delete(security: 'is_granted("'.AbstractVoter::DELETE.'", object)'),
        new Put(security: 'is_granted("'.AbstractVoter::EDIT.'", object)'),
        new Patch(security: 'is_granted("'.AbstractVoter::EDIT.'", object)'),
        new GetCollection(),
        new Post(
            openapiContext: [
                'requestBody' => [
                    'content' => [
                        'application/json' => [
                            'examples' => [
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
                                        'description' => 'The definition name',
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
    input: RenditionInput::class,
    output: AssetRenditionOutput::class,
    provider: RenditionCollectionProvider::class,
)]
#[ORM\Table]
#[ORM\UniqueConstraint(name: 'uniq_representation', columns: ['definition_id', 'asset_id'])]
#[ORM\Entity(repositoryClass: AssetRenditionRepository::class)]
class AssetRendition extends AbstractUuidEntity
{
    use CreatedAtTrait;
    use UpdatedAtTrait;
    final public const GROUP_READ = 'assetrend:read';
    final public const GROUP_LIST = 'assetrend:index';

    #[ORM\ManyToOne(targetEntity: RenditionDefinition::class, inversedBy: 'renditions')]
    #[ORM\JoinColumn(nullable: false)]
    private ?RenditionDefinition $definition = null;

    #[ORM\ManyToOne(targetEntity: Asset::class, inversedBy: 'renditions')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Asset $asset = null;

    #[ORM\ManyToOne(targetEntity: File::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?File $file = null;

    /**
     * Homothetic and same format has original.
     */
    #[Groups([AssetRendition::GROUP_LIST, AssetRendition::GROUP_READ])]
    #[ORM\Column(type: Types::BOOLEAN, nullable: true)]
    private ?bool $projection = null;

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

    public function getDefinition(): RenditionDefinition
    {
        return $this->definition;
    }

    public function setDefinition(RenditionDefinition $definition): void
    {
        $this->definition = $definition;
    }

    #[ApiProperty]
    #[Groups([AssetRendition::GROUP_LIST, AssetRendition::GROUP_READ, Asset::GROUP_LIST, Asset::GROUP_READ])]
    public function getName(): string
    {
        return $this->definition->getName();
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
}
