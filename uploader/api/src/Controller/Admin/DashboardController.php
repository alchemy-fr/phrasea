<?php

namespace App\Controller\Admin;

use Alchemy\AdminBundle\Controller\AbstractAdminDashboardController;
use Alchemy\AdminBundle\Controller\Acl\AccessControlEntryCrudController;
use Alchemy\AdminBundle\Controller\MultipartUploadCrudController;
use Alchemy\ConfiguratorBundle\Controller\ConfiguratorEntryCrudController;
use Alchemy\NotifierBundle\Controller\Admin\NotificationCrudController;
use Alchemy\NotifierBundle\Controller\Admin\NotificationPreferenceCrudController;
use Alchemy\NotifierBundle\Controller\Admin\SubscriberCrudController;
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
            MenuItem::linkToRoute('Target params permissions', '', 'alchemy_admin_acl_global_permissions', ['type' => 'target_params']),
            MenuItem::linkToRoute('Form schema permissions', '', 'alchemy_admin_acl_global_permissions', ['type' => 'form_schema']),
            MenuItem::linkTo(AccessControlEntryCrudController::class, 'All permissions (advanced)', ''),
        ];

        $submenu2 = [
            MenuItem::linkTo(TargetCrudController::class, 'Target', ''),
            MenuItem::linkTo(CommitCrudController::class, 'Commit', ''),
            MenuItem::linkTo(AssetCrudController::class, 'Asset', ''),
            MenuItem::linkTo(MultipartUploadCrudController::class, 'MultipartUpload', ''),
        ];

        $submenu3 = [
            MenuItem::linkTo(FormSchemaCrudController::class, 'FormSchema', ''),
            MenuItem::linkTo(TargetParamsCrudController::class, 'TargetParams', ''),
        ];

        yield MenuItem::subMenu('Permissions', 'fas fa-folder-open')->setSubItems($submenu1);
        yield MenuItem::subMenu('Uploads', 'fas fa-folder-open')->setSubItems($submenu2);
        yield MenuItem::subMenu('Data', 'fas fa-folder-open')->setSubItems($submenu3);
        yield MenuItem::subMenu('Notification', 'fas fa-bell')->setSubItems([
            MenuItem::linkTo(SubscriberCrudController::class, 'Subscribers'),
            MenuItem::linkTo(NotificationCrudController::class, 'In-app notifications'),
            MenuItem::linkTo(NotificationPreferenceCrudController::class, 'Preferences'),
        ]);
        yield MenuItem::linkTo(ConfiguratorEntryCrudController::class, 'Global Config', 'fa fa-gear');
        yield $this->createDevMenu();
    }
}
