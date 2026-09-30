<?php

declare(strict_types=1);

namespace App\Model;

use Alchemy\AuthBundle\Security\JwtUser;
use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use App\Api\Provider\MetadataTagProvider;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * A metadata tag namespace (e.g. "IPTC") or tag (e.g. "IPTC:Keywords") known by exiftool,
 * used to suggest tag names when configuring attribute definitions.
 *
 * GET /metadata-tags?query=IPT        => the namespaces containing "IPT"
 * GET /metadata-tags?query=IPTC:Key   => the tags of the "IPTC" namespace containing "Key"
 */
#[ApiResource(
    shortName: 'metadata-tag',
    operations: [
        new GetCollection(),
    ],
    normalizationContext: [
        'groups' => [self::GROUP_READ],
    ],
    security: 'is_granted("'.JwtUser::IS_AUTHENTICATED_FULLY.'")',
    provider: MetadataTagProvider::class,
)]
final readonly class MetadataTag
{
    private const string GROUP_READ = 'metadata-tag:r';

    public function __construct(
        #[Groups(self::GROUP_READ)]
        #[ApiProperty(identifier: true)]
        public string $id,
        #[Groups(self::GROUP_READ)]
        public string $namespace,
        /** Null for a namespace entry */
        #[Groups(self::GROUP_READ)]
        public ?string $name = null,
        #[Groups(self::GROUP_READ)]
        public ?string $description = null,
        #[Groups(self::GROUP_READ)]
        public ?bool $writable = null,
        #[Groups(self::GROUP_READ)]
        public ?bool $multi = null,
    ) {
    }
}
