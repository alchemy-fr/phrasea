<?php

declare(strict_types=1);

namespace Alchemy\AdminBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

abstract class AbstractAdminController extends AbstractController
{
    private string $siteTitle;
    private ?string $siteLogo;
    private ?string $dashBoardMenuUrl;

    public function __construct(string $siteTitle, ?string $siteLogo, ?string $dashBoardBaseUrl, bool $servicesMenuEnabled)
    {
        $this->siteTitle = $siteTitle;
        $this->siteLogo = $siteLogo;
        $this->dashBoardMenuUrl = $servicesMenuEnabled ? sprintf('%s/menu.html', $dashBoardBaseUrl) : null;
    }

    protected function getLayoutParams(): array
    {
        return [
            'site_title' => $this->siteTitle,
            'site_logo' => $this->siteLogo,
            'dashboard_menu_url' => $this->dashBoardMenuUrl,
        ];
    }
}
