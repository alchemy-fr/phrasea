<?php

declare(strict_types=1);

namespace App\Model;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use App\Api\Provider\RenditionBuildModuleProvider;
use Symfony\Component\Serializer\Annotation\Groups;

#[ApiResource(
    shortName: 'rendition-build-module',
    operations: [
        new Get(
            uriTemplate: '/rendition-build-modules/{id}',
        ),
        new GetCollection(
            uriTemplate: '/rendition-build-modules',
        ),
    ],
    normalizationContext: [
        'groups' => [self::GROUP_READ],
    ],
    provider: RenditionBuildModuleProvider::class,
)]
class RenditionBuildModule
{
    private const string GROUP_READ = 'rend-bm:r';

    #[ApiProperty(identifier: true)]
    #[Groups(self::GROUP_READ)]
    public ?string $id = null;

    #[Groups(self::GROUP_READ)]
    public string $name = '';

    #[Groups(self::GROUP_READ)]
    public ?string $description = null;

    #[Groups(self::GROUP_READ)]
    public string $reference = '';

    public function getId(): ?string
    {
        return $this->id;
    }
}
