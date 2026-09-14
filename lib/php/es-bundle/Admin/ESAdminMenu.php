<?php

declare(strict_types=1);

namespace Alchemy\ESBundle\Admin;

use Alchemy\ESBundle\Controller\Admin\ESIndexAdminController;
use EasyCorp\Bundle\EasyAdminBundle\Config\Menu\RouteMenuItem;
use EasyCorp\Bundle\EasyAdminBundle\Config\MenuItem;

/**
 * Menu entry to the Elasticsearch indices & aliases screen, to be yielded from
 * an application DashboardController (requires easycorp/easyadmin-bundle).
 */
final class ESAdminMenu
{
    public static function createMenuItem(string $label = 'ES Indices & Aliases', string $icon = 'fa fa-magnifying-glass', string $dashboardRouteName = 'easyadmin'): RouteMenuItem
    {
        return MenuItem::linkToRoute($label, $icon, ESIndexAdminController::routeName(ESIndexAdminController::ROUTE_INDEX, $dashboardRouteName));
    }
}
