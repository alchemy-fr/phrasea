<?php

namespace App\Controller\Admin;

use Alchemy\AdminBundle\Controller\AbstractAdminDashboardController;
use Alchemy\AuthBundle\Security\JwtUser;
use App\Entity\Contact;
use App\Entity\TopicSubscriber;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\MenuItem;

class DashboardController extends AbstractAdminDashboardController
{
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
            MenuItem::linkToRoute('PHP Info', '', 'alchemy_admin_phpinfo'),
        ];

        yield MenuItem::linkToCrud('Contact', 'fas fa-folder-open', Contact::class);
        yield MenuItem::linkToCrud('TopicSubscriber', 'fas fa-folder-open', TopicSubscriber::class);
        yield MenuItem::subMenu('Dev', 'fas fa-folder-open')->setSubItems($submenu1)->setPermission(JwtUser::ROLE_TECH);
    }
}
