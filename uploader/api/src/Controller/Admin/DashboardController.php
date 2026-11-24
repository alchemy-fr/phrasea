<?php

namespace App\Controller\Admin;

use Alchemy\AdminBundle\Controller\AbstractAdminDashboardController;
use Alchemy\AdminBundle\Controller\Acl\AccessControlEntryCrudController;
use Alchemy\AdminBundle\Controller\MultipartUploadCrudController;
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
        yield MenuItem::subMenu('Permissions', 'fa-solid fa-shield-halved')->setSubItems([
            MenuItem::linkToRoute('Target params permissions', 'fa-solid fa-sliders', GlobalPermissionsController::ROUTE_TARGET_DATA),
            MenuItem::linkToRoute('Form schema permissions', 'fa-solid fa-table-list', GlobalPermissionsController::ROUTE_FORM_SCHEMA),
            MenuItem::linkToRoute('Upload link permissions', 'fa-solid fa-link', GlobalPermissionsController::ROUTE_UPLOAD_LINK),
            MenuItem::linkTo(AccessControlEntryCrudController::class, 'All permissions (advanced)', 'fa-solid fa-user-shield'),
        ]);

        yield MenuItem::subMenu('Uploads', 'fa-solid fa-cloud-arrow-up')->setSubItems([
            MenuItem::linkTo(TargetCrudController::class, 'Targets', 'fa-solid fa-bullseye'),
            MenuItem::linkTo(CommitCrudController::class, 'Commits', 'fa-solid fa-code-commit'),
            MenuItem::linkTo(AssetCrudController::class, 'Assets', 'fa-solid fa-image'),
            MenuItem::linkTo(MultipartUploadCrudController::class, 'Multipart Uploads', 'fa-solid fa-upload'),
        ]);

        yield NotifierAdminMenu::createMenuItem();
        yield $this->createSettingsMenu();
        yield $this->createDevMenu();
    }
}
