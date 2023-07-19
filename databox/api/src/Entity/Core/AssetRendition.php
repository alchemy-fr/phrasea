<?php

declare(strict_types=1);

namespace App\Entity\Core;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use App\Api\Model\Input\RenditionInput;
use App\Api\Provider\RenditionCollectionDataProvider;
use App\Entity\AbstractUuidEntity;
use App\Entity\Traits\CreatedAtTrait;
use App\Entity\Traits\UpdatedAtTrait;
use App\Repository\Core\AssetRenditionRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

#[ApiResource(
    shortName: 'rendition',
    operations: [
        new Get(security: 'is_granted("READ", object)'),
        new Delete(security: 'is_granted("DELETE", object)'),
        new Put(security: 'is_granted("EDIT", object)'),
        new Patch(security: 'is_granted("EDIT", object)'),
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
            input: RenditionInput::class
        ),
    ],
    normalizationContext: [
        'groups' => [AssetRendition::GROUP_LIST],
    ],
    provider: RenditionCollectionDataProvider::class,
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

    #[Groups([AssetRendition::GROUP_LIST, AssetRendition::GROUP_READ])]
    #[ORM\ManyToOne(targetEntity: RenditionDefinition::class, inversedBy: 'renditions')]
    #[ORM\JoinColumn(nullable: false)]
    private ?RenditionDefinition $definition = null;

    #[Groups([AssetRendition::GROUP_LIST, AssetRendition::GROUP_READ])]
    #[ORM\ManyToOne(targetEntity: Asset::class, inversedBy: 'renditions')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Asset $asset = null;

    #[Groups([AssetRendition::GROUP_LIST, AssetRendition::GROUP_READ, Asset::GROUP_LIST, Asset::GROUP_READ])]
    #[ORM\ManyToOne(targetEntity: File::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?File $file = null;

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
}
