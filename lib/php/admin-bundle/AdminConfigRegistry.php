<?php

declare(strict_types=1);

namespace Alchemy\AdminBundle;

final readonly class AdminConfigRegistry
{
    public function __construct(
        private string $siteTitle,
        private ?string $siteLogo,
    ) {
    }

    public function getLayoutParams(): array
    {
        return [
            'site_title' => $this->siteTitle,
            'site_logo' => $this->siteLogo,
        ];
    }

    public function getSiteTitle(): string
    {
        return $this->siteTitle;
    }

    public function getSiteLogo(): ?string
    {
        return $this->siteLogo;
    }
}
