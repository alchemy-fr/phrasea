<?php

declare(strict_types=1);

namespace Alchemy\AdminBundle\Controller;

use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Menu\SubMenuItem;
use EasyCorp\Bundle\EasyAdminBundle\Config\MenuItem;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractDashboardController;
use EasyCorp\Bundle\EasyAdminBundle\Config\Dashboard;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use EasyCorp\Bundle\EasyAdminBundle\Config\UserMenu;
use Symfony\Component\Security\Core\User\UserInterface;

abstract class AbstractAdminDashboardController extends AbstractDashboardController
{
    private string $siteTitle;
    private ?string $siteLogo;

    /**
     * @Route("/admin")
     */
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
            ->overrideTemplate('crud/index', '@AlchemyAdmin/list.html.twig');
    }

    public function configureUserMenu(UserInterface $user): UserMenu
    {
        return (parent::configureUserMenu($user))
            ->displayUserAvatar(false);
    }

    public function setSiteTitle(string $siteTitle): void
    {
        $this->siteTitle = $siteTitle;
    }

    public function setSiteLogo(?string $siteLogo): void
    {
        $this->siteLogo = $siteLogo;
    }

    protected function getLayoutParams(): array
    {
        return [
            'site_title' => $this->siteTitle,
            'site_logo' => $this->siteLogo,
        ];
    }

    public function configureDashboard(): Dashboard
    {
        return Dashboard::new()
            ->setTitle('<div>' . ($this->siteLogo ?: '') . '<div>' . $this->siteTitle. '</div></div>');
    }

    protected function createDevMenu(string $failedEventClass): SubMenuItem
    {
        $submenu2 = [
            MenuItem::linkToCrud('FailedEvent', '', $failedEventClass)->setPermission('ROLE_TECH'),
            MenuItem::linkToRoute('PHP Info', '', 'alchemy_admin_phpinfo')->setPermission('ROLE_TECH'),
        ];

        return MenuItem::subMenu('Dev', 'fas fa-folder-open')->setSubItems($submenu2)->setPermission('ROLE_TECH');
    }
}
