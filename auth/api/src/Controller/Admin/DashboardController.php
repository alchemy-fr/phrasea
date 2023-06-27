<?php

namespace App\Controller\Admin;

use Alchemy\AdminBundle\Controller\AbstractAdminDashboardController;
use Alchemy\OAuthServerBundle\Entity\AccessToken;
use Alchemy\OAuthServerBundle\Entity\OAuthClient;
use App\Entity\FailedEvent;
use App\Entity\Group;
use App\Entity\User;
use EasyCorp\Bundle\EasyAdminBundle\Config\MenuItem;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class DashboardController extends AbstractAdminDashboardController
{
    #[Route(path: '/admin')]
    public function index(): Response
    {
        $adminUrlGenerator = $this->container->get(AdminUrlGenerator::class);

        return $this->redirect($adminUrlGenerator->setController(UserCrudController::class)->generateUrl());
    }

    public function configureMenuItems(): iterable
    {
        $submenu1 = [
            MenuItem::linkToCrud('User', '', User::class),
            MenuItem::linkToCrud('Group', '', Group::class),
            MenuItem::linkToCrud('AccessToken', '', AccessToken::class),
        ];

        yield MenuItem::linkToCrud('OAuth Clients', 'fas fa-folder-open', OAuthClient::class)
            ->setPermission('ROLE_ADMIN_OAUTH_CLIENTS');

        yield MenuItem::subMenu('Users', 'fas fa-folder-open')->setSubItems($submenu1);
        yield $this->createDevMenu(FailedEvent::class);
    }
}
