<?php

namespace App\Controller\Admin;

use Alchemy\AdminBundle\Controller\AbstractAdminDashboardController;
use Alchemy\AdminBundle\Controller\Acl\AccessControlEntryCrudController;
use Alchemy\AdminBundle\Controller\MultipartUploadCrudController;
use Alchemy\ConfiguratorBundle\Controller\ConfiguratorEntryCrudController;
use Alchemy\NotifierBundle\Admin\NotifierAdminMenu;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminDashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\MenuItem;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Component\HttpFoundation\Response;

#[AdminDashboard(routePath: '/admin', routeName: 'easyadmin')]
class DashboardController extends AbstractAdminDashboardController
{
    public function index(): Response
    {
        $adminUrlGenerator = $this->container->get(AdminUrlGenerator::class);

        return $this->redirect($adminUrlGenerator->setController(AssetCrudController::class)->generateUrl());
    }

    public function configureMenuItems(): iterable
    {
        $submenu1 = [
            MenuItem::linkToRoute('Target params permissions', '', GlobalPermissionsController::ROUTE_TARGET_DATA),
            MenuItem::linkToRoute('Form schema permissions', '', GlobalPermissionsController::ROUTE_FORM_SCHEMA),
            MenuItem::linkTo(AccessControlEntryCrudController::class, 'All permissions (advanced)', ''),
        ];

        $submenu2 = [
            MenuItem::linkTo(TargetCrudController::class, 'Target', ''),
            MenuItem::linkTo(CommitCrudController::class, 'Commit', ''),
            MenuItem::linkTo(AssetCrudController::class, 'Asset', ''),
            MenuItem::linkTo(MultipartUploadCrudController::class, 'MultipartUpload', ''),
        ];

        yield MenuItem::subMenu('Permissions', 'fas fa-folder-open')->setSubItems($submenu1);
        yield MenuItem::subMenu('Uploads', 'fas fa-folder-open')->setSubItems($submenu2);
        yield NotifierAdminMenu::createMenuItem();
        yield MenuItem::linkTo(ConfiguratorEntryCrudController::class, 'Global Config', 'fa fa-gear');
        yield $this->createDevMenu();
    }
}
