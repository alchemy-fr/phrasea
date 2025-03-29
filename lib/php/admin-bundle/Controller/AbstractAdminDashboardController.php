<?php

declare(strict_types=1);

namespace Alchemy\AdminBundle\Controller;

use Alchemy\AdminBundle\AdminConfigRegistry;
use Alchemy\AuthBundle\Security\JwtUser;
use Alchemy\MessengerBundle\Entity\MessengerMessage;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Dashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\Menu\SubMenuItem;
use EasyCorp\Bundle\EasyAdminBundle\Config\MenuItem;
use EasyCorp\Bundle\EasyAdminBundle\Config\UserMenu;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractDashboardController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Contracts\Service\Attribute\Required;

abstract class AbstractAdminDashboardController extends AbstractDashboardController
{
    private AdminConfigRegistry $adminConfigRegistry;

    #[Route(path: '/admin', name: 'easyadmin')]
    public function index(): Response
    {
        return $this->render('@AlchemyAdmin/layout.html.twig');
    }

    public function configureCrud(): Crud
    {
        return Crud::new()
            ->setDateFormat('dd/MM/yyyy')
            ->setDateTimeFormat('dd/MM/yyyy HH:mm:ss')
            ->setTimeFormat('HH:mm')
            ->overrideTemplate('layout', '@AlchemyAdmin/layout.html.twig')
            ->overrideTemplate('crud/index', '@AlchemyAdmin/list.html.twig')
            ->overrideTemplate('crud/detail', '@AlchemyAdmin/detail.html.twig')
        ;
    }

    public function configureUserMenu(UserInterface $user): UserMenu
    {
        return parent::configureUserMenu($user)
            ->displayUserAvatar(false);
    }

    public function configureDashboard(): Dashboard
    {
        return Dashboard::new()
            ->setFaviconPath('favicon.ico')
            ->setTitle('<div>'.($this->adminConfigRegistry->getSiteLogo() ?: '').'<div>'.$this->adminConfigRegistry->getSiteTitle().'</div></div>');
    }

    protected function createDevMenu(): SubMenuItem
    {
        $subMenu = [];
        if (class_exists(MessengerMessage::class)) {
            $subMenu[] = MenuItem::linkToCrud('Messenger Failed Event', 'fa fa-bug', MessengerMessage::class);
        }
        $subMenu[] = MenuItem::linkToRoute('PHP Info', 'fa fa-info', 'alchemy_admin_phpinfo')->setPermission(JwtUser::ROLE_TECH);
        $subMenu[] = MenuItem::linkToRoute('Queues', 'fa fa-prescription-bottle', 'alchemy_admin_queues_list')->setPermission(JwtUser::ROLE_TECH);

        return MenuItem::subMenu('Dev', 'fa fa-code')->setSubItems($subMenu)->setPermission(JwtUser::ROLE_TECH);
    }

    #[Required]
    public function setAdminConfigRegistry(AdminConfigRegistry $adminConfigRegistry): void
    {
        $this->adminConfigRegistry = $adminConfigRegistry;
    }
}
