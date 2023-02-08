<?php

namespace App\Controller\Admin;

use Alchemy\AclBundle\Entity\AccessControlEntry;
use Alchemy\AdminBundle\Controller\AbstractAdminDashboardController;
use Alchemy\OAuthServerBundle\Entity\OAuthClient;
use Alchemy\StorageBundle\Entity\MultipartUpload;
use App\Entity\Asset;
use App\Entity\EnvVar;
use App\Entity\FailedEvent;
use App\Entity\Publication;
use App\Entity\PublicationAsset;
use App\Entity\PublicationProfile;
use App\Entity\SubDefinition;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\MenuItem;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;


class DashboardController extends AbstractAdminDashboardController
{
    /**
     * @Route("/admin")
     */
    public function index(): Response
    {
        $adminUrlGenerator = $this->container->get(AdminUrlGenerator::class);

        return $this->redirect($adminUrlGenerator->setController(FailedEventCrudController::class)->generateUrl());
    }


    public function configureCrud(): Crud
    {
        return Crud::new()
            ->setDateFormat('dd/MM/yyyy')
            ->setDateTimeFormat('dd/MM/yyyy HH:mm:ss')
            ->setTimeFormat('HH:mm')
            ->overrideTemplate('layout', '@AlchemyAdmin/layout.html.twig')
            ->overrideTemplate('crud/index', '@AlchemyAdmin/list.html.twig');
    }

    public function configureMenuItems(): iterable
    {
        $submenu1 = [
            MenuItem::linktoRoute('Publication permissions', '', 'admin_global_permissions', ['type' => 'publication']),
            MenuItem::linktoRoute('Profile permissions', '', 'admin_global_permissions', ['type' => 'profile']),
            MenuItem::linkToCrud('All permissions (advanced)', '', AccessControlEntry::class),
        ];

        $submenu2 = [
            MenuItem::linkToCrud('Publication', '', Publication::class),
            MenuItem::linkToCrud('Profile', '', PublicationProfile::class),
            MenuItem::linkToCrud('Asset', '', Asset::class),
            MenuItem::linkToCrud('SubDefinition', '', SubDefinition::class),
            MenuItem::linkToCrud('PublicationAsset', '', PublicationAsset::class),
            MenuItem::linkToCrud('MultipartUpload', '', MultipartUpload::class),
        ];

        $submenu3 = [
            MenuItem::linkToCrud('FailedEvent', '', FailedEvent::class),
            MenuItem::linktoRoute('PHP Info', '', 'alchemy_admin_phpinfo'),
        ];

        yield MenuItem::subMenu('Permissions', 'fas fa-folder-open')->setSubItems($submenu1);
        yield MenuItem::subMenu('Publications', 'fas fa-folder-open')->setSubItems($submenu2);
        yield MenuItem::linkToCrud('OAuth Clients', 'fas fa-folder-open', OAuthClient::class)->setPermission('ROLE_ADMIN_OAUTH_CLIENTS');
        yield MenuItem::linkToCrud('EnvVar', 'fas fa-folder-open', EnvVar::class);
        yield MenuItem::subMenu('Dev', 'fas fa-folder-open')->setSubItems($submenu3)->setPermission('ROLE_TECH');
    }
}
