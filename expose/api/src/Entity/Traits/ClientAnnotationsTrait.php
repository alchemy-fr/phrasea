<?php

declare(strict_types=1);

namespace App\Entity\Traits;

use ApiPlatform\Core\Annotation\ApiProperty;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * Free annotations for client consuming the API.
 */
trait ClientAnnotationsTrait
{
    /**
     * @ApiProperty()
     *
     * @ORM\Column(type="text", nullable=true)
     *
     * @Groups({"publication:admin:read", "asset:admin:read", "profile:admin:read"})
     */
    private ?string $clientAnnotations = null;

    public function getClientAnnotations(): ?string
    {
        return $this->clientAnnotations;
    }

    public function setClientAnnotations(?string $clientAnnotations): void
    {
        $this->clientAnnotations = $clientAnnotations;
    }
}
