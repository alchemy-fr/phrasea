<?php

namespace App\Controller\Admin;

use Alchemy\OAuthServerBundle\Entity\AccessToken;
use Alchemy\OAuthServerBundle\Entity\OAuthClient;
use App\Entity\FailedEvent;
use App\Entity\Group;
use App\Entity\User;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Dashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\MenuItem;
use EasyCorp\Bundle\EasyAdminBundle\Config\UserMenu;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractDashboardController;
use Symfony\Component\Security\Core\User\UserInterface;

use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class DashboardController extends AbstractDashboardController
{
    /**
     * @Route("/admin")
     */
    public function index(): Response
    {
//        // redirect to some CRUD controller
//        $routeBuilder = $this->get(AdminUrlGenerator::class);
//
//        return $this->redirect($routeBuilder->setController(OneOfYourCrudController::class)->generateUrl());
//
//        // you can also redirect to different pages depending on the current user
//        if ('jane' === $this->getUser()->getUsername()) {
//            return $this->redirect('...');
//        }
//
        // you can also render some template to display a proper Dashboard
        // (tip: it's easier if your template extends from @EasyAdmin/page/content.html.twig)
        return $this->render('@AlchemyAdmin/layout.html.twig');
    }

    public function configureDashboard(): Dashboard
    {
        return Dashboard::new()
            ->setTitle('<div><img src="https://www.phraseanet.com/wp-content/uploads/2014/05/PICTO_PHRASEANET.png" width="80px" title="Auth" alt="Auth" /><div>Auth</div></div>');
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
            MenuItem::linkToCrud('User', '', User::class),
            MenuItem::linkToCrud('Group', '', Group::class),
            MenuItem::linkToCrud('AccessToken', '', AccessToken::class),
        ];

        $submenu2 = [
            MenuItem::linkToCrud('FailedEvent', '', FailedEvent::class)->setPermission('ROLE_TECH'),
            MenuItem::linkToRoute('PHP Info', '', 'alchemy_admin_phpinfo')->setPermission('ROLE_TECH'),
        ];

        yield MenuItem::subMenu('Users', 'fas fa-folder-open')->setSubItems($submenu1);
        yield MenuItem::linkToCrud('OAuth Clients', 'fas fa-folder-open', OAuthClient::class)->setPermission('ROLE_ADMIN_OAUTH_CLIENTS');
        yield MenuItem::subMenu('Dev', 'fas fa-folder-open')->setSubItems($submenu2)->setPermission('ROLE_TECH');
    }
}
