<?php

declare(strict_types=1);

namespace App\Model;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use App\Api\Provider\IntegrationTypeProvider;
use Symfony\Component\Serializer\Annotation\Groups;

#[ApiResource(
    shortName: 'integration-type',
    operations: [
        new Get(
            uriTemplate: '/integration-types/{id}',
        ),
        new GetCollection(),
    ],
    normalizationContext: [
        'groups' => [self::GROUP_READ],
    ],
    provider: IntegrationTypeProvider::class,
)]
class IntegrationType
{
    private const string GROUP_READ = 'integration-t:read';

    #[ApiProperty(identifier: true)]
    #[Groups(self::GROUP_READ)]
    public ?string $id = null;

    #[Groups(self::GROUP_READ)]
    public string $name = '';

    #[Groups(self::GROUP_READ)]
    public string $title = '';

    #[Groups(self::GROUP_READ)]
    public string $reference = '';

    public function getId(): ?string
    {
        return $this->id;
    }

    public static function normalizeId(string $id): string
    {
        return str_replace('.', '--', $id);
    }

    public static function denormalizeId(string $id): string
    {
        return str_replace('--', '.', $id);
    }
}
