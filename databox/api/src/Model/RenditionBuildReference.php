<?php

declare(strict_types=1);

namespace App\Model;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use App\Api\Provider\RenditionBuildReferenceProvider;
use Symfony\Component\Serializer\Annotation\Groups;

#[ApiResource(
    shortName: 'rendition-build-reference',
    operations: [
        new Get(
            uriTemplate: '/rendition-build-reference',
        ),
    ],
    normalizationContext: [
        'groups' => [self::GROUP_READ],
    ],
    provider: RenditionBuildReferenceProvider::class,
)]
class RenditionBuildReference
{
    private const string GROUP_READ = 'rend-br:r';

    #[ApiProperty(identifier: true)]
    #[Groups(self::GROUP_READ)]
    public string $id = 'rendition-build-reference';

    /**
     * The global build definition structure reference.
     */
    #[Groups(self::GROUP_READ)]
    public string $reference = '';

    /**
     * One reference section per available module.
     *
     * @var array<array{name: string, description: string|null, reference: string}>
     */
    #[Groups(self::GROUP_READ)]
    public array $references = [];

    public function getId(): string
    {
        return $this->id;
    }
}
