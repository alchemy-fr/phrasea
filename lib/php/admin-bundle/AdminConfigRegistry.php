<?php

declare(strict_types=1);

namespace Alchemy\AdminBundle;

class AdminConfigRegistry
{
    public function __construct(private readonly string $siteTitle, private readonly ?string $siteLogo)
    {
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
