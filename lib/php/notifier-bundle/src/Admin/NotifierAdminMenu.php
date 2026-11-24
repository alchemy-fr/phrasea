<?php

declare(strict_types=1);

namespace Alchemy\NotifierBundle\Admin;

use Alchemy\NotifierBundle\Controller\Admin\BroadcastCrudController;
use Alchemy\NotifierBundle\Controller\Admin\NotificationCrudController;
use Alchemy\NotifierBundle\Controller\Admin\NotificationDigestCrudController;
use Alchemy\NotifierBundle\Controller\Admin\NotificationPreferenceCrudController;
use Alchemy\NotifierBundle\Controller\Admin\SubscriberCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Config\Menu\SubMenuItem;
use EasyCorp\Bundle\EasyAdminBundle\Config\MenuItem;

/**
 * The "Notifications" admin submenu, shared by every application dashboard so
 * a new notifier screen only has to be added here.
 *
 * Requires easycorp/easyadmin-bundle in the application (like the CRUD
 * controllers it links to).
 */
final class NotifierAdminMenu
{
    public static function createMenuItem(): SubMenuItem
    {
        return MenuItem::subMenu('Notifications', 'fa-solid fa-bell')->setSubItems([
            MenuItem::linkTo(BroadcastCrudController::class, 'Broadcasts', 'fa-solid fa-bullhorn'),
            MenuItem::linkTo(SubscriberCrudController::class, 'Subscribers', 'fa-solid fa-user-check'),
            MenuItem::linkTo(NotificationCrudController::class, 'In-app Notifications', 'fa-solid fa-inbox'),
            MenuItem::linkTo(NotificationDigestCrudController::class, 'Pending Digests', 'fa-solid fa-envelope-open-text'),
            MenuItem::linkTo(NotificationPreferenceCrudController::class, 'Preferences', 'fa-solid fa-user-gear'),
        ]);
    }
}
