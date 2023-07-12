<?php

declare(strict_types=1);

namespace App\Api\Model\Output;

use ApiPlatform\Core\Annotation\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use Symfony\Component\Serializer\Annotation\Groups;

#[ApiResource(normalizationContext: [
    'force_resource_class' => true,
])]
#[Get]
abstract class AbstractUuidOutput
{
    /**
     * The unique resource ID (UUID form).
     */
    #[Groups(['_'])]
    #[ApiProperty(identifier: true)]
    protected string $id;

    public function getId(): string
    {
        return $this->id;
    }

    public function setId(string $id): void
    {
        $this->id = $id;
    }
}
