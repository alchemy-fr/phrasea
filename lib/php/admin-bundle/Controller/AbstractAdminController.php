<?php

declare(strict_types=1);

namespace Alchemy\AdminBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

abstract class AbstractAdminController extends AbstractController
{
    private string $siteTitle;
    private ?string $siteLogo;

    public function setSiteTitle(string $siteTitle): void
    {
        $this->siteTitle = $siteTitle;
    }

    public function setSiteLogo(?string $siteLogo): void
    {
        $this->siteLogo = $siteLogo;
    }

    protected function getLayoutParams(): array
    {
        return [
            'site_title' => $this->siteTitle,
            'site_logo' => $this->siteLogo,
        ];
    }
}
