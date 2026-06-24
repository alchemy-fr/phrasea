<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use Alchemy\AdminBundle\Controller\AbstractAdminDashboardController;
use Alchemy\AdminBundle\Controller\Acl\AccessControlEntryCrudController;
use Alchemy\AdminBundle\Controller\MultipartUploadCrudController;
use Alchemy\ConfiguratorBundle\Controller\ConfiguratorEntryCrudController;
use Alchemy\TrackBundle\Controller\ChangeLogCrudController;
use Alchemy\WebhookBundle\Controller\WebhookCrudController;
use Alchemy\WebhookBundle\Controller\WebhookLogCrudController;
use App\Entity\Basket\Basket;
use App\Entity\Core\Asset;
use App\Entity\Core\Collection;
use App\Entity\Core\Workspace;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminDashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\MenuItem;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Component\HttpFoundation\Response;

#[AdminDashboard(routePath: '/admin', routeName: 'easyadmin')]
class DashboardController extends AbstractAdminDashboardController
{
    #[\Override]
    public function index(): Response
    {
        /** @var AdminUrlGenerator $adminUrlGenerator */
        $adminUrlGenerator = $this->container->get(AdminUrlGenerator::class);

        return $this->redirect($adminUrlGenerator
            ->setDashboard(DashboardController::class)
            ->setController(WorkspaceCrudController::class)
            ->generateUrl());
    }

    #[\Override]
    public function configureMenuItems(): iterable
    {
        $submenu1 = [
            MenuItem::linkToRoute('Asset permissions', '', 'alchemy_admin_acl_global_permissions', ['type' => Asset::OBJECT_TYPE]),
            MenuItem::linkToRoute('Collection permissions', '', 'alchemy_admin_acl_global_permissions', ['type' => Collection::OBJECT_TYPE]),
            MenuItem::linkToRoute('Workspace permissions', '', 'alchemy_admin_acl_global_permissions', ['type' => Workspace::OBJECT_TYPE]),
            MenuItem::linkToRoute('Basket permissions', '', 'alchemy_admin_acl_global_permissions', ['type' => Basket::OBJECT_TYPE]),
            MenuItem::linkTo(AccessControlEntryCrudController::class, 'All permissions (advanced)'),
        ];

        $submenu2 = [
            MenuItem::linkTo(WorkspaceCrudController::class, 'Workspace'),
            MenuItem::linkTo(CollectionCrudController::class, 'Collection'),
            MenuItem::linkTo(CollectionAccessCrudController::class, 'Collection Access'),
            MenuItem::linkTo(AssetCrudController::class, 'Asset'),
            MenuItem::linkTo(FileCrudController::class, 'File'),
            MenuItem::linkTo(AssetAttachmentCrudController::class, 'Attachment'),
            MenuItem::linkTo(MultipartUploadCrudController::class, 'Multipart Upload'),
            MenuItem::linkTo(AttributeCrudController::class, 'Attribute'),
            MenuItem::linkTo(AttributeEntityCrudController::class, 'Attribute Entity'),
            MenuItem::linkTo(EntityListCrudController::class, 'Entity List'),
            MenuItem::linkTo(AttributeDefinitionCrudController::class, 'Attribute Definition'),
            MenuItem::linkTo(AttributePolicyCrudController::class, 'Attribute Policy'),
            MenuItem::linkTo(TagCrudController::class, 'Tag'),
            MenuItem::linkTo(TagFilterRuleCrudController::class, 'Tag Filter Rule'),
            MenuItem::linkTo(AssetRenditionCrudController::class, 'Asset Rendition'),
            MenuItem::linkTo(RenditionDefinitionCrudController::class, 'Rendition Definition'),
            MenuItem::linkTo(RenditionPolicyCrudController::class, 'Rendition Policy'),
            MenuItem::linkTo(AlternateUrlCrudController::class, 'Alternate URL'),
            MenuItem::linkTo(ShareCrudController::class, 'Share'),
        ];

        $pages = [
            MenuItem::linkTo(PageCrudController::class, 'Page', 'fa fa-file'),
        ];

        $basket = [
            MenuItem::linkTo(BasketCrudController::class, 'Basket'),
            MenuItem::linkTo(BasketAssetCrudController::class, 'Basket Assets'),
        ];

        $lists = [
            MenuItem::linkTo(ProfileCrudController::class, 'Profiles'),
            MenuItem::linkTo(ProfileItemCrudController::class, 'Lists Items'),
            MenuItem::linkTo(SavedSearchCrudController::class, 'Saved Searches'),
        ];

        $submenuTemplates = [
            MenuItem::linkTo(AssetDataTemplateCrudController::class, 'Asset Data Template'),
            MenuItem::linkTo(TemplateAttributeCrudController::class, 'Template Attribute'),
            MenuItem::linkTo(WorkspaceTemplateCrudController::class, 'Workspace Templates'),
        ];

        $submenu3 = [
            MenuItem::linkTo(OperationTaskCrudController::class, 'Operation Task'),
            MenuItem::linkTo(PopulatePassCrudController::class, 'Populate Pass'),
            MenuItem::linkTo(ESIndexStateCrudController::class, 'ES Index State'),
        ];

        $submenu4 = [
            MenuItem::linkTo(WorkspaceIntegrationCrudController::class, 'Integration', 'fa fa-gear'),
            MenuItem::linkTo(IntegrationDataCrudController::class, 'Integration Data', 'fa fa-database'),
            MenuItem::linkTo(IntegrationTokenCrudController::class, 'Integration Token', 'fa fa-fingerprint'),
            MenuItem::linkTo(WorkspaceEnvCrudController::class, 'Env', 'fa fa-database'),
            MenuItem::linkTo(WorkspaceSecretCrudController::class, 'Secret', 'fa fa-lock'),
            MenuItem::linkToRoute('Help', 'fa fa-question', 'admin_integrations_help'),
        ];

        $webhookSubMenu = [
            MenuItem::linkTo(WebhookCrudController::class, 'Webhook'),
            MenuItem::linkTo(WebhookLogCrudController::class, 'Webhook Error'),
        ];

        $workflows = [
            MenuItem::linkTo(WorkflowStateCrudController::class, 'Workflows State'),
            MenuItem::linkTo(JobStateCrudController::class, 'Job State'),
        ];

        $discussions = [
            MenuItem::linkTo(ThreadCrudController::class, 'Threads'),
        ];

        $logs = [
            MenuItem::linkTo(ActionLogCrudController::class, 'Action Log'),
            MenuItem::linkTo(ChangeLogCrudController::class, 'Change Log'),
        ];

        yield MenuItem::subMenu('Permission', 'fas fa-lock')->setSubItems($submenu1);
        yield MenuItem::subMenu('Core', 'fas fa-database')->setSubItems($submenu2);
        yield MenuItem::subMenu('Page', 'fas fa-file')->setSubItems($pages);
        yield MenuItem::subMenu('Basket', 'fas fa-basket-shopping')->setSubItems($basket);
        yield MenuItem::subMenu('Lists', 'fas fa-list')->setSubItems($lists);
        yield MenuItem::subMenu('Admin', 'fas fa-folder-open')->setSubItems($submenu3);
        yield MenuItem::subMenu('Template', 'fas fa-align-justify')->setSubItems($submenuTemplates);
        yield MenuItem::subMenu('Integration', 'fas fa-gear')->setSubItems($submenu4);
        yield MenuItem::subMenu('Workflow', 'fas fa-gears')->setSubItems($workflows);
        yield MenuItem::subMenu('Webhook', 'fas fa-network-wired')->setSubItems($webhookSubMenu);
        yield MenuItem::subMenu('Discussions', 'fas fa-message')->setSubItems($discussions);
        yield MenuItem::subMenu('Logs', 'fa fa-history')->setSubItems($logs);
        yield MenuItem::linkToRoute('Notification', 'fas fa-bell', 'alchemy_notify_admin_index');
        yield MenuItem::linkTo(ConfiguratorEntryCrudController::class, 'Global Config', 'fa fa-gear');
        yield $this->createDevMenu();
    }
}
