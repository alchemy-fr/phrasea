<?php

declare(strict_types=1);

namespace Alchemy\AdminBundle;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

final readonly class AdminConfigRegistry
{
    public function __construct(
        #[Autowire(param: 'alchemy_admin.site_title')]
        private string $siteTitle,
        #[Autowire(param: 'alchemy_admin.logo')]
        private ?array $logo,
    ) {
    }

    public function getLayoutParams(): array
    {
        return [
            'site_title' => $this->getSiteTitle(),
            'site_logo' => $this->getLogo(),
        ];
    }

    public function getSiteTitle(): string
    {
        return $this->siteTitle;
    }

    public function getLogo(): ?string
    {
        $logo = $this->logo ?? [
            'src' => '/bundles/alchemyadmin/phrasea-logo.png',
            'style' => 'max-width: 80px;',
        ];

        if (empty($logo['src'])) {
            return null;
        }

        return sprintf(
            '<img src="%1$s" style="%2$s" title="%3$s" alt="%3$s" />',
            $logo['src'],
            $logo['style'] ?? '',
            $this->getSiteTitle(),
        );
    }
}
