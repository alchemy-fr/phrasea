<?php

declare(strict_types=1);

namespace App\Entity\Traits;

use ApiPlatform\Metadata\ApiProperty;
use App\Entity\Publication;
use App\Entity\PublicationProfile;
use Symfony\Component\Serializer\Annotation\Groups;

trait CapabilitiesTrait
{
    #[Groups(['_', Publication::GROUP_INDEX, PublicationProfile::GROUP_INDEX])]
    #[ApiProperty(types: ['object'])]
    protected array $capabilities = [];

    public function getCapabilities(): array
    {
        return $this->capabilities;
    }

    public function setCapabilities(array $capabilities): void
    {
        $this->capabilities = $capabilities;
    }
}
