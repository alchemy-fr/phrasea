<?php

namespace App\Controller\Admin;

use Alchemy\AclBundle\Entity\AccessControlEntry;
use Alchemy\OAuthServerBundle\Entity\OAuthClient;
use Alchemy\StorageBundle\Entity\MultipartUpload;
use App\Entity\Asset;
use App\Entity\Commit;
use App\Entity\FailedEvent;
use App\Entity\FormSchema;
use App\Entity\Target;
use App\Entity\TargetParams;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Dashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\MenuItem;
use EasyCorp\Bundle\EasyAdminBundle\Config\UserMenu;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractDashboardController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\User\UserInterface;


class DashboardController extends AbstractDashboardController
{
    /**
     * @Route("/admin")
     */
    public function index(): Response
    {
        return $this->render('@AlchemyAdmin/layout.html.twig');
    }

    public function configureDashboard(): Dashboard
    {
        return Dashboard::new()
            ->setTitle('<div><img src="https://www.phraseanet.com/wp-content/uploads/2014/05/PICTO_PHRASEANET.png" width="80px" title="Uploader" alt="Uploader" /><div>Uploader</div></div>');
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

    public function configureUserMenu(UserInterface $user): UserMenu
    {
        return (parent::configureUserMenu($user))
            ->displayUserAvatar(false);
    }

    public function configureMenuItems(): iterable
    {
        $submenu1 = [
            MenuItem::linkToRoute('Target params permissions', '', 'admin_global_permissions', ['type' => 'target_params']),
            MenuItem::linkToRoute('Form schema permissions', '', 'admin_global_permissions', ['type' => 'form_schema']),
            MenuItem::linkToCrud('All permissions (advanced)', '', AccessControlEntry::class),
        ];

        $submenu2 = [
            MenuItem::linkToCrud('Target', '', Target::class),
            MenuItem::linkToCrud('Commit', '', Commit::class),
            MenuItem::linkToCrud('Asset', '', Asset::class),
            MenuItem::linkToCrud('MultipartUpload', '', MultipartUpload::class),
        ];

        $submenu3 = [
            MenuItem::linkToCrud('FormSchema', '', FormSchema::class),
            MenuItem::linkToCrud('TargetParams', '', TargetParams::class),
        ];

        $submenu4 = [
            MenuItem::linkToCrud('FailedEvent', '', FailedEvent::class),
            MenuItem::linkToRoute('PHP Info', '', 'alchemy_admin_phpinfo'),
        ];

        yield MenuItem::subMenu('Permissions', 'fas fa-folder-open')->setSubItems($submenu1);
        yield MenuItem::subMenu('Uploads', 'fas fa-folder-open')->setSubItems($submenu2);
        yield MenuItem::subMenu('Data', 'fas fa-folder-open')->setSubItems($submenu3);
        yield MenuItem::linkToCrud('OAuth Clients', 'fas fa-folder-open', OAuthClient::class)->setPermission('ROLE_ADMIN_OAUTH_CLIENTS');
        yield MenuItem::subMenu('Dev', 'fas fa-folder-open')->setSubItems($submenu4)->setPermission('ROLE_TECH');
    }
}
