<?php

declare(strict_types=1);

namespace App\Api\Model\Output;

use ApiPlatform\Metadata\ApiProperty;
use App\Api\Model\Output\Traits\CreatedAtDTOTrait;
use App\Api\Model\Output\Traits\UpdatedAtDTOTrait;
use App\Entity\Core\AssetImport;
use Symfony\Component\Serializer\Attribute\Groups;

class AssetImportOutput extends AbstractUuidOutput
{
    use CreatedAtDTOTrait;
    use UpdatedAtDTOTrait;

    #[Groups([AssetImport::GROUP_READ])]
    public ?string $workspaceId = null;

    #[Groups([AssetImport::GROUP_READ])]
    public ?string $collectionId = null;

    #[Groups([AssetImport::GROUP_READ])]
    public ?string $collectionTitle = null;

    #[Groups([AssetImport::GROUP_READ])]
    public ?string $integrationId = null;

    #[Groups([AssetImport::GROUP_READ])]
    public ?string $integrationTitle = null;

    #[Groups([AssetImport::GROUP_READ])]
    public ?UserOutput $owner = null;

    #[ApiProperty(jsonSchemaContext: [
        'type' => 'string',
        'enum' => AssetImport::FORMATS,
    ])]
    #[Groups([AssetImport::GROUP_READ])]
    public ?string $format = null;

    #[Groups([AssetImport::GROUP_READ])]
    public ?int $privacy = null;

    #[Groups([AssetImport::GROUP_READ])]
    public ?string $originalName = null;

    #[ApiProperty(jsonSchemaContext: [
        'type' => 'string',
        'enum' => ['pending', 'dispatching', 'processing', 'completed', 'failed'],
    ])]
    #[Groups([AssetImport::GROUP_READ])]
    public ?string $status = null;

    /**
     * The number of entries, unknown until the whole file is read.
     */
    #[Groups([AssetImport::GROUP_READ])]
    public ?int $total = null;

    #[Groups([AssetImport::GROUP_READ])]
    public int $created = 0;

    /**
     * The entries whose key matches an asset of the workspace.
     */
    #[Groups([AssetImport::GROUP_READ])]
    public int $skipped = 0;

    #[Groups([AssetImport::GROUP_READ])]
    public int $failed = 0;

    #[Groups([AssetImport::GROUP_READ])]
    public ?string $error = null;

    #[Groups([AssetImport::GROUP_READ])]
    public ?\DateTimeImmutable $endedAt = null;
}
