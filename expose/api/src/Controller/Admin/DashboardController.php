<?php

namespace App\Controller\Admin;

use Alchemy\AdminBundle\Controller\AbstractAdminDashboardController;
use Alchemy\AdminBundle\Controller\Acl\AccessControlEntryCrudController;
use Alchemy\AdminBundle\Controller\MultipartUploadCrudController;
use Alchemy\ConfiguratorBundle\Controller\ConfiguratorEntryCrudController;
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

        return $this->redirect($adminUrlGenerator->setController(PublicationCrudController::class)->generateUrl());
    }

    public function configureMenuItems(): iterable
    {
        $submenu1 = [
            MenuItem::linktoRoute('Publication permissions', '', 'alchemy_admin_acl_global_permissions', ['type' => 'publication']),
            MenuItem::linktoRoute('Profile permissions', '', 'alchemy_admin_acl_global_permissions', ['type' => 'profile']),
            MenuItem::linkTo(AccessControlEntryCrudController::class, 'All permissions (advanced)', ''),
        ];

        $submenu2 = [
            MenuItem::linkTo(PublicationCrudController::class, 'Publication', ''),
            MenuItem::linkTo(PublicationProfileCrudController::class, 'Profile', ''),
            MenuItem::linkTo(AssetCrudController::class, 'Asset', ''),
            MenuItem::linkTo(SubDefinitionCrudController::class, 'SubDefinition', ''),
            MenuItem::linkTo(MultipartUploadCrudController::class, 'MultipartUpload', ''),
        ];

        yield MenuItem::subMenu('Permissions', 'fas fa-folder-open')->setSubItems($submenu1);
        yield MenuItem::subMenu('Publications', 'fas fa-folder-open')->setSubItems($submenu2);
        yield MenuItem::linkTo(EnvVarCrudController::class, 'EnvVar', 'fas fa-folder-open');
        yield MenuItem::linkToRoute('Notification', 'fas fa-bell', 'alchemy_notify_admin_index');
        yield MenuItem::linkTo(ConfiguratorEntryCrudController::class, 'Global Config', 'fa fa-gear');
        yield $this->createDevMenu();
    }
}
