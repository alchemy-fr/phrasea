<?php

declare(strict_types=1);

namespace App\Api\Model\Output;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use Symfony\Component\Serializer\Annotation\Groups;

#[ApiResource(normalizationContext: [
    'force_resource_class' => true,
])]
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
