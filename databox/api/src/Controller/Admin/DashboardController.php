<?php

namespace App\Controller\Admin;

use Alchemy\AclBundle\Entity\AccessControlEntry;
use Alchemy\OAuthServerBundle\Entity\AccessToken;
use Alchemy\OAuthServerBundle\Entity\OAuthClient;
use Alchemy\WebhookBundle\Entity\Webhook;
use Alchemy\WebhookBundle\Entity\WebhookLog;
use App\Entity\Admin\ESIndexState;
use App\Entity\Admin\PopulatePass;
use App\Entity\Core\AlternateUrl;
use App\Entity\Core\Asset;
use App\Entity\Core\AssetRendition;
use App\Entity\Core\AssetTitleAttribute;
use App\Entity\Core\Attribute;
use App\Entity\Core\AttributeClass;
use App\Entity\Core\AttributeDefinition;
use App\Entity\Core\Collection;
use App\Entity\Core\File;
use App\Entity\Core\RenditionClass;
use App\Entity\Core\RenditionDefinition;
use App\Entity\Core\RenditionRule;
use App\Entity\Core\Tag;
use App\Entity\Core\TagFilterRule;
use App\Entity\Core\Workspace;
use App\Entity\FailedEvent;
use App\Entity\Integration\WorkspaceIntegration;
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
            ->setTitle('<div><img src="https://www.phraseanet.com/wp-content/uploads/2014/05/PICTO_PHRASEANET.png" width="80px" title="Databox" alt="Databox" /><div>Databox</div></div>');
    }

    public function configureCrud(): Crud
    {
        return Crud::new()
            ->setDateFormat('dd/MM/yyyy')
            ->setDateTimeFormat('dd/MM/yyyy HH:mm:ss')
            ->setTimeFormat('HH:mm')
            ->overrideTemplate('layout', '@AlchemyAdmin/layout.html.twig')
            ->overrideTemplate('crud/index', '@AlchemyAdmin/list.html.twig')
            ;
    }

    public function configureUserMenu(UserInterface $user): UserMenu
    {
        return (parent::configureUserMenu($user))
            ->displayUserAvatar(false);
    }


    public function configureMenuItems(): iterable
    {
        $submenu1 = [
            MenuItem::linkToRoute('Asset permissions', '', 'admin_global_permissions', ['type' => 'asset']),
            MenuItem::linkToRoute('Collection permissions', '', 'admin_global_permissions', ['type' => 'collection']),
            MenuItem::linkToRoute('Workspace permissions', '', 'admin_global_permissions', ['type' => 'workspace']),
            MenuItem::linkToCrud('All permissions (advanced)', '', AccessControlEntry::class),
            MenuItem::linkToCrud('Access tokens', '', AccessToken::class),
        ];

        $submenu2 = [
            MenuItem::linkToCrud('Workspace', '', Workspace::class),
            MenuItem::linkToCrud('Collection', '', Collection::class),
            MenuItem::linkToCrud('Asset', '', Asset::class),
            MenuItem::linkToCrud('File', '', File::class),
            MenuItem::linkToCrud('Attribute', '', Attribute::class),
            MenuItem::linkToCrud('AssetTitleAttribute', '', AssetTitleAttribute::class),
            MenuItem::linkToCrud('AttributeDefinition', '', AttributeDefinition::class),
            MenuItem::linkToCrud('AttributeClass', '', AttributeClass::class),
            MenuItem::linkToCrud('Tag', '', Tag::class),
            MenuItem::linkToCrud('TagFilterRule', '', TagFilterRule::class),
            MenuItem::linkToCrud('AssetRendition', '', AssetRendition::class),
            MenuItem::linkToCrud('RenditionDefinition', '', RenditionDefinition::class),
            MenuItem::linkToCrud('RenditionClass', '', RenditionClass::class),
            MenuItem::linkToCrud('RenditionRule', '', RenditionRule::class),
            MenuItem::linkToCrud('AlternateUrl', '', AlternateUrl::class),
        ];

        $submenu3 = [
            MenuItem::linkToCrud('PopulatePass', '', PopulatePass::class),
            MenuItem::linkToCrud('ESIndexState', '', ESIndexState::class),
        ];

        $submenu4 = [
            MenuItem::linkToCrud('Integration', '', WorkspaceIntegration::class),
            MenuItem::linkToRoute('Help', '', 'admin_integrations_help'),
        ];

        $submenu5 = [
            MenuItem::linkToCrud('FailedEvent', '', FailedEvent::class),
            MenuItem::linkToRoute('PHP Info', '', 'alchemy_admin_phpinfo'),
        ];

        $submenu6 = [
            MenuItem::linkToCrud('Webhooks', '', Webhook::class),
            MenuItem::linkToCrud('Webhook errors', '', WebhookLog::class),
        ];

        yield MenuItem::subMenu('Permissions', 'fas fa-folder-open')->setSubItems($submenu1);
        yield MenuItem::subMenu('Core', 'fas fa-folder-open')->setSubItems($submenu2);
        yield MenuItem::subMenu('Admin', 'fas fa-folder-open')->setSubItems($submenu3);
        yield MenuItem::linkToCrud('OAuthClient', 'fas fa-folder-open', OAuthClient::class);
        yield MenuItem::subMenu('Integrations', 'fas fa-folder-open')->setSubItems($submenu4);
        yield MenuItem::subMenu('Dev', 'fas fa-folder-open')->setSubItems($submenu5);
        yield MenuItem::subMenu('Webhooks', 'fas fa-folder-open')->setSubItems($submenu6);
    }
}
