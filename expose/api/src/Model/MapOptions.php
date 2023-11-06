<?php

declare(strict_types=1);

namespace App\Model;

use Symfony\Component\Serializer\Annotation\Groups;

class MapOptions extends AbstractOptions
{
    #[Groups(['profile:read', 'publication:admin:read'])]
    private ?float $lat = null;

    #[Groups(['profile:read', 'publication:admin:read'])]
    private ?float $lng = null;

    #[Groups(['profile:read', 'publication:admin:read'])]
    private ?int $zoom = null;

    #[Groups(['profile:read', 'publication:admin:read'])]
    private ?string $mapLayout = null;

    public function jsonSerialize(): array
    {
        return array_filter([
            'lat' => $this->lat,
            'lng' => $this->lng,
            'zoom' => $this->zoom,
            'mapLayout' => $this->mapLayout,
        ]);
    }

    public function fromJson(array $options = []): void
    {
        $this->lat = $options['lat'] ?? null;
        $this->lng = $options['lng'] ?? null;
        $this->zoom = $options['zoom'] ?? null;
        $this->mapLayout = $options['mapLayout'] ?? null;
    }

    public function getLat(): ?float
    {
        return $this->lat;
    }

    public function setLat(?float $lat): void
    {
        $this->lat = $lat;
    }

    public function getLng(): ?float
    {
        return $this->lng;
    }

    public function setLng(?float $lng): void
    {
        $this->lng = $lng;
    }

    public function getZoom(): ?int
    {
        return $this->zoom;
    }

    public function setZoom(?int $zoom): void
    {
        $this->zoom = $zoom;
    }

    public function getMapLayout(): ?string
    {
        return $this->mapLayout;
    }

    public function setMapLayout(?string $mapLayout): void
    {
        $this->mapLayout = $mapLayout;
    }
}
