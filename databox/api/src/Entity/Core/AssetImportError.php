<?php

declare(strict_types=1);

namespace App\Entity\Core;

use Alchemy\AuthBundle\Security\JwtUser;
use Alchemy\CoreBundle\Entity\AbstractUuidEntity;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\QueryParameter;
use App\Api\Provider\AssetImportErrorCollectionProvider;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

/**
 * An entry of an import that could not be imported.
 */
#[ApiResource(
    shortName: 'asset-import-error',
    operations: [
        new GetCollection(
            uriTemplate: '/asset-imports/{id}/errors',
            uriVariables: [
                'id' => new Link(toProperty: 'import', fromClass: AssetImport::class),
            ],
            parameters: [
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
            provider: AssetImportErrorCollectionProvider::class,
        ),
    ],
    normalizationContext: [
        'groups' => ['_', self::GROUP_READ],
    ],
    security: 'is_granted("'.JwtUser::IS_AUTHENTICATED_FULLY.'")',
)]
#[ORM\Entity]
#[ORM\Index(columns: ['import_id', 'line'], name: 'asset_import_error_line_idx')]
class AssetImportError extends AbstractUuidEntity
{
    final public const string GROUP_READ = 'asset-import-error:r';

    #[ORM\ManyToOne(targetEntity: AssetImport::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?AssetImport $import = null;

    /**
     * The line of the entry in the NDJSON file, or its position (from 1) in the JSON list.
     */
    #[Groups([self::GROUP_READ])]
    #[ORM\Column(type: Types::INTEGER, nullable: false)]
    private int $line;

    #[Groups([self::GROUP_READ])]
    #[ORM\Column(name: 'entry_key', type: Types::STRING, length: 255, nullable: true)]
    private ?string $key;

    #[Groups([self::GROUP_READ])]
    #[ORM\Column(type: Types::TEXT, nullable: false)]
    private string $message;

    public function __construct(AssetImport $import, int $line, ?string $key, string $message)
    {
        parent::__construct();
        $this->import = $import;
        $this->line = $line;
        $this->key = null !== $key ? mb_substr($key, 0, 255) : null;
        $this->message = $message;
    }

    public function getImport(): ?AssetImport
    {
        return $this->import;
    }

    public function getLine(): int
    {
        return $this->line;
    }

    public function getKey(): ?string
    {
        return $this->key;
    }

    public function getMessage(): string
    {
        return $this->message;
    }
}
