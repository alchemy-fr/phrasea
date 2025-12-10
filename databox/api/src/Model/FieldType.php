<?php

declare(strict_types=1);

namespace App\Model;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use App\Api\Provider\FieldTypeProvider;

#[ApiResource(
    shortName: 'field-type',
    operations: [
        new Get(),
        new GetCollection(),
    ],
    normalizationContext: ['enable_max_depth' => true],
    provider: FieldTypeProvider::class,
)]
class FieldType
{
    #[ApiProperty(identifier: true)]
    private string $name;

    private string $title;

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): void
    {
        $this->title = $title;
    }
}
