<?php

declare(strict_types=1);

namespace App\Entity\Traits;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * Free annotations for client consuming the API.
 */
trait ClientAnnotationsTrait
{
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['publication:admin:read', 'asset:admin:read', 'profile:admin:read'])]
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
