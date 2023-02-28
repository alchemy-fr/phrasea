<?php

declare(strict_types=1);

namespace Alchemy\AdminBundle;

class AdminConfigRegistry
{
    private string $siteTitle;
    private ?string $siteLogo;

    public function __construct(string $siteTitle, ?string $siteLogo)
    {
        $this->siteTitle = $siteTitle;
        $this->siteLogo = $siteLogo;
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
