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

        return $this->redirect($adminUrlGenerator->setController(PublicationCrudController::class)->generateUrl());
    }

    public function configureMenuItems(): iterable
    {
        yield MenuItem::subMenu('Permissions', 'fa-solid fa-shield-halved')->setSubItems([
            MenuItem::linkTo(PublicationCrudController::class, 'Publication permissions', 'fa-solid fa-newspaper')->setAction('globalPermissions'),
            MenuItem::linkTo(PublicationProfileCrudController::class, 'Profile permissions', 'fa-solid fa-id-card')->setAction('globalPermissions'),
            MenuItem::linkTo(AccessControlEntryCrudController::class, 'All permissions (advanced)', 'fa-solid fa-user-shield'),
        ]);

        yield MenuItem::subMenu('Publications', 'fa-solid fa-newspaper')->setSubItems([
            MenuItem::linkTo(PublicationCrudController::class, 'Publications', 'fa-solid fa-newspaper'),
            MenuItem::linkTo(PublicationProfileCrudController::class, 'Profiles', 'fa-solid fa-id-card'),
            MenuItem::linkTo(AssetCrudController::class, 'Assets', 'fa-solid fa-image'),
            MenuItem::linkTo(SubDefinitionCrudController::class, 'Sub-definitions', 'fa-solid fa-images'),
            MenuItem::linkTo(MultipartUploadCrudController::class, 'Multipart Uploads', 'fa-solid fa-upload'),
        ]);

        yield NotifierAdminMenu::createMenuItem();
        yield $this->createSettingsMenu([
            MenuItem::linkTo(EnvVarCrudController::class, 'Env Vars', 'fa-solid fa-terminal'),
        ]);
        yield $this->createDevMenu();
    }
}
