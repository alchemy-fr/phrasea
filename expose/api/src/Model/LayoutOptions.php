<?php

declare(strict_types=1);

namespace App\Model;

use App\Entity\Publication;
use App\Entity\PublicationProfile;
use Symfony\Component\Serializer\Annotation\Groups;

class LayoutOptions extends AbstractOptions
{
    /**
     * Compatible with [gallery].
     */
    #[Groups([PublicationProfile::GROUP_READ, Publication::GROUP_ADMIN_READ])]
    private ?bool $displayMap = null;

    /**
     * Display pins on map instead of thumbnails
     * Compatible with [mapbox].
     */
    #[Groups([PublicationProfile::GROUP_READ, Publication::GROUP_ADMIN_READ])]
    private ?bool $displayMapPins = null;

    #[Groups([PublicationProfile::GROUP_READ, Publication::GROUP_ADMIN_READ])]
    private ?string $logoUrl = null;

    public function jsonSerialize(): array
    {
        return array_filter([
            'displayMap' => $this->displayMap,
            'displayMapPins' => $this->displayMapPins,
            'logoUrl' => $this->logoUrl,
        ]);
    }

    public function fromJson(array $options = []): void
    {
        $this->displayMap = $options['displayMap'] ?? null;
        $this->displayMapPins = $options['displayMapPins'] ?? null;
        $this->logoUrl = $options['logoUrl'] ?? null;
    }

    public function isDisplayMap(): ?bool
    {
        return $this->displayMap;
    }

    public function setDisplayMap(?bool $displayMap): void
    {
        $this->displayMap = $displayMap;
    }

    public function getDisplayMapPins(): ?bool
    {
        return $this->displayMapPins;
    }

    public function setDisplayMapPins(?bool $displayMapPins): void
    {
        $this->displayMapPins = $displayMapPins;
    }

    public function getLogoUrl(): ?string
    {
        return $this->logoUrl;
    }

    public function setLogoUrl(?string $logoUrl): void
    {
        $this->logoUrl = $logoUrl;
    }
}
