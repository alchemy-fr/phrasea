<?php

namespace App\Controller\Admin;

use Alchemy\AclBundle\Entity\AccessControlEntry;
use Alchemy\AdminBundle\Controller\AbstractAdminDashboardController;
use Alchemy\ConfiguratorBundle\Entity\ConfiguratorEntry;
use Alchemy\StorageBundle\Entity\MultipartUpload;
use Alchemy\WebhookBundle\Entity\Webhook;
use Alchemy\WebhookBundle\Entity\WebhookLog;
use Alchemy\Workflow\Doctrine\Entity\JobState;
use App\Entity\Admin\ESIndexState;
use App\Entity\Admin\PopulatePass;
use App\Entity\Basket\Basket;
use App\Entity\Basket\BasketAsset;
use App\Entity\Core\AlternateUrl;
use App\Entity\Core\Asset;
use App\Entity\Core\AssetRendition;
use App\Entity\Core\AssetTitleAttribute;
use App\Entity\Core\Attribute;
use App\Entity\Core\AttributeClass;
use App\Entity\Core\AttributeDefinition;
use App\Entity\Core\AttributeEntity;
use App\Entity\AttributeList\AttributeList;
use App\Entity\Core\Collection;
use App\Entity\Core\File;
use App\Entity\Core\RenditionClass;
use App\Entity\Core\RenditionDefinition;
use App\Entity\Core\RenditionRule;
use App\Entity\Core\Share;
use App\Entity\Core\Tag;
use App\Entity\Core\TagFilterRule;
use App\Entity\Core\Workspace;
use App\Entity\Integration\IntegrationData;
use App\Entity\Integration\IntegrationToken;
use App\Entity\Integration\WorkspaceEnv;
use App\Entity\Integration\WorkspaceIntegration;
use App\Entity\Integration\WorkspaceSecret;
use App\Entity\Template\AssetDataTemplate;
use App\Entity\Template\TemplateAttribute;
use App\Entity\Template\WorkspaceTemplate;
use App\Entity\Workflow\WorkflowState;
use EasyCorp\Bundle\EasyAdminBundle\Config\MenuItem;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class DashboardController extends AbstractAdminDashboardController
{
    #[Route(path: '/admin', name: 'easyadmin')]
    public function index(): Response
    {
        $adminUrlGenerator = $this->container->get(AdminUrlGenerator::class);

        return $this->redirect($adminUrlGenerator->setController(WorkspaceCrudController::class)->generateUrl());
    }

    public function configureMenuItems(): iterable
    {
        $submenu1 = [
            MenuItem::linkToRoute('Asset permissions', '', 'alchemy_admin_acl_global_permissions', ['type' => 'asset']),
            MenuItem::linkToRoute('Collection permissions', '', 'alchemy_admin_acl_global_permissions', ['type' => 'collection']),
            MenuItem::linkToRoute('Workspace permissions', '', 'alchemy_admin_acl_global_permissions', ['type' => 'workspace']),
            MenuItem::linkToRoute('Basket permissions', '', 'alchemy_admin_acl_global_permissions', ['type' => 'basket']),
            MenuItem::linkToCrud('All permissions (advanced)', '', AccessControlEntry::class),
        ];

        $submenu2 = [
            MenuItem::linkToCrud('Workspace', '', Workspace::class),
            MenuItem::linkToCrud('Collection', '', Collection::class),
            MenuItem::linkToCrud('Asset', '', Asset::class),
            MenuItem::linkToCrud('File', '', File::class),
            MenuItem::linkToCrud('Multipart Upload', '', MultipartUpload::class),
            MenuItem::linkToCrud('Attribute', '', Attribute::class),
            MenuItem::linkToCrud('Attribute Entity', '', AttributeEntity::class),
            MenuItem::linkToCrud('Asset Title Attribute', '', AssetTitleAttribute::class),
            MenuItem::linkToCrud('Attribute Definition', '', AttributeDefinition::class),
            MenuItem::linkToCrud('Attribute Class', '', AttributeClass::class),
            MenuItem::linkToCrud('Attribute List', '', AttributeList::class),
            MenuItem::linkToCrud('Tag', '', Tag::class),
            MenuItem::linkToCrud('Tag Filter Rule', '', TagFilterRule::class),
            MenuItem::linkToCrud('Asset Rendition', '', AssetRendition::class),
            MenuItem::linkToCrud('Rendition Definition', '', RenditionDefinition::class),
            MenuItem::linkToCrud('Rendition Class', '', RenditionClass::class),
            MenuItem::linkToCrud('Rendition Rule', '', RenditionRule::class),
            MenuItem::linkToCrud('Alternate URL', '', AlternateUrl::class),
            MenuItem::linkToCrud('Share', '', Share::class),
        ];

        $basket = [
            MenuItem::linkToCrud('Basket', '', Basket::class),
            MenuItem::linkToCrud('Basket Assets', '', BasketAsset::class),
        ];

        $submenuTemplates = [
            MenuItem::linkToCrud('Asset Data Template', '', AssetDataTemplate::class),
            MenuItem::linkToCrud('Template Attribute', '', TemplateAttribute::class),
            MenuItem::linkToCrud('Workspace Templates', '', WorkspaceTemplate::class),
        ];

        $submenu3 = [
            MenuItem::linkToCrud('Populate Pass', '', PopulatePass::class),
            MenuItem::linkToCrud('ES Index State', '', ESIndexState::class),
        ];

        $submenu4 = [
            MenuItem::linkToCrud('Integration', 'fa fa-gear', WorkspaceIntegration::class),
            MenuItem::linkToCrud('Integration Data', 'fa fa-database', IntegrationData::class),
            MenuItem::linkToCrud('Integration Token', 'fa fa-fingerprint', IntegrationToken::class),
            MenuItem::linkToCrud('Env', 'fa fa-database', WorkspaceEnv::class),
            MenuItem::linkToCrud('Secret', 'fa fa-lock', WorkspaceSecret::class),
            MenuItem::linkToRoute('Help', 'fa fa-question', 'admin_integrations_help'),
        ];

        $webhookSubMenu = [
            MenuItem::linkToCrud('Webhook', '', Webhook::class),
            MenuItem::linkToCrud('Webhook Error', '', WebhookLog::class),
        ];

        $workflows = [
            MenuItem::linkToCrud('Workflows State', '', WorkflowState::class),
            MenuItem::linkToCrud('Job State', '', JobState::class),
        ];

        yield MenuItem::subMenu('Permission', 'fas fa-lock')->setSubItems($submenu1);
        yield MenuItem::subMenu('Core', 'fas fa-database')->setSubItems($submenu2);
        yield MenuItem::subMenu('Basket', 'fas fa-basket-shopping')->setSubItems($basket);
        yield MenuItem::subMenu('Admin', 'fas fa-folder-open')->setSubItems($submenu3);
        yield MenuItem::subMenu('Template', 'fas fa-align-justify')->setSubItems($submenuTemplates);
        yield MenuItem::subMenu('Integration', 'fas fa-gear')->setSubItems($submenu4);
        yield MenuItem::subMenu('Workflow', 'fas fa-gears')->setSubItems($workflows);
        yield MenuItem::subMenu('Webhook', 'fas fa-network-wired')->setSubItems($webhookSubMenu);
        yield MenuItem::linkToRoute('Notification', 'fas fa-bell', 'alchemy_notify_admin_index');
        yield MenuItem::linkToCrud('Global Config', 'fa fa-gear', ConfiguratorEntry::class);
        yield $this->createDevMenu();
    }
}
