<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use Alchemy\AdminBundle\Controller\AbstractAdminDashboardController;
use Alchemy\AdminBundle\Controller\Acl\AccessControlEntryCrudController;
use Alchemy\AdminBundle\Controller\MultipartUploadCrudController;
use Alchemy\ESBundle\Admin\ESAdminMenu;
use Alchemy\NotifierBundle\Admin\NotifierAdminMenu;
use Alchemy\TrackBundle\Controller\ChangeLogCrudController;
use Alchemy\WebhookBundle\Controller\WebhookCrudController;
use Alchemy\WebhookBundle\Controller\WebhookLogCrudController;
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
        yield MenuItem::subMenu('Permissions', 'fa-solid fa-shield-halved')->setSubItems([
            MenuItem::linkTo(WorkspaceCrudController::class, 'Workspace permissions', 'fa-solid fa-briefcase')->setAction('globalPermissions'),
            MenuItem::linkTo(CollectionCrudController::class, 'Collection permissions', 'fa-solid fa-folder')->setAction('globalPermissions'),
            MenuItem::linkTo(AssetCrudController::class, 'Asset permissions', 'fa-solid fa-image')->setAction('globalPermissions'),
            MenuItem::linkTo(BasketCrudController::class, 'Basket permissions', 'fa-solid fa-basket-shopping')->setAction('globalPermissions'),
            MenuItem::linkTo(AccessControlEntryCrudController::class, 'All permissions (advanced)', 'fa-solid fa-user-shield'),
        ]);

        yield MenuItem::subMenu('Assets', 'fa-solid fa-photo-film')->setSubItems([
            MenuItem::linkTo(WorkspaceCrudController::class, 'Workspaces', 'fa-solid fa-briefcase'),
            MenuItem::linkTo(CollectionCrudController::class, 'Collections', 'fa-solid fa-folder'),
            MenuItem::linkTo(CollectionAccessCrudController::class, 'Collection Access', 'fa-solid fa-door-open'),
            MenuItem::linkTo(AssetCrudController::class, 'Assets', 'fa-solid fa-image'),
            MenuItem::linkTo(FileCrudController::class, 'Files', 'fa-solid fa-file'),
            MenuItem::linkTo(AssetAttachmentCrudController::class, 'Attachments', 'fa-solid fa-paperclip'),
            MenuItem::linkTo(AlternateUrlCrudController::class, 'Alternate URLs', 'fa-solid fa-link'),
            MenuItem::linkTo(AssetEmbeddingCrudController::class, 'Asset Embeddings', 'fa-solid fa-brain'),
            MenuItem::linkTo(AssetPolicyCrudController::class, 'Asset Policies', 'fa-solid fa-scale-balanced'),
            MenuItem::linkTo(AssetExportCrudController::class, 'Asset Exports', 'fa-solid fa-file-export'),
            MenuItem::linkTo(MultipartUploadCrudController::class, 'Multipart Uploads', 'fa-solid fa-upload'),
        ]);

        yield MenuItem::subMenu('Attributes', 'fa-solid fa-tags')->setSubItems([
            MenuItem::linkTo(AttributeDefinitionCrudController::class, 'Attribute Definitions', 'fa-solid fa-tag'),
            MenuItem::linkTo(AttributeCrudController::class, 'Attributes', 'fa-solid fa-pen-to-square'),
            MenuItem::linkTo(AttributeEntityCrudController::class, 'Attribute Entities', 'fa-solid fa-cubes'),
            MenuItem::linkTo(EntityListCrudController::class, 'Entity Lists', 'fa-solid fa-list-ul'),
            MenuItem::linkTo(AttributePolicyCrudController::class, 'Attribute Policies', 'fa-solid fa-scale-balanced'),
            MenuItem::linkTo(AttributeFilterRuleCrudController::class, 'Attribute Filter Rules', 'fa-solid fa-filter'),
        ]);

        yield MenuItem::subMenu('Renditions', 'fa-solid fa-images')->setSubItems([
            MenuItem::linkTo(RenditionDefinitionCrudController::class, 'Rendition Definitions', 'fa-solid fa-wand-magic-sparkles'),
            MenuItem::linkTo(RenditionPolicyCrudController::class, 'Rendition Policies', 'fa-solid fa-scale-balanced'),
            MenuItem::linkTo(AssetRenditionCrudController::class, 'Asset Renditions', 'fa-solid fa-images'),
        ]);

        yield MenuItem::subMenu('Templates', 'fa-solid fa-clone')->setSubItems([
            MenuItem::linkTo(WorkspaceTemplateCrudController::class, 'Workspace Templates', 'fa-solid fa-briefcase'),
            MenuItem::linkTo(AssetDataTemplateCrudController::class, 'Data Templates', 'fa-solid fa-file-lines'),
            MenuItem::linkTo(TemplateAttributeCrudController::class, 'Template Attributes', 'fa-solid fa-table-list'),
        ]);

        yield MenuItem::subMenu('Sharing', 'fa-solid fa-share-nodes')->setSubItems([
            MenuItem::linkTo(ShareCrudController::class, 'Shares', 'fa-solid fa-share-nodes'),
            MenuItem::linkTo(BasketCrudController::class, 'Baskets', 'fa-solid fa-basket-shopping'),
            MenuItem::linkTo(BasketAssetCrudController::class, 'Basket Assets', 'fa-solid fa-boxes-stacked'),
            MenuItem::linkTo(ThreadCrudController::class, 'Threads', 'fa-solid fa-comments'),
            MenuItem::linkTo(PageCrudController::class, 'Pages', 'fa-solid fa-newspaper'),
        ]);

        yield MenuItem::subMenu('Searches', 'fa-solid fa-magnifying-glass')->setSubItems([
            MenuItem::linkTo(ProfileCrudController::class, 'Profiles', 'fa-solid fa-address-card'),
            MenuItem::linkTo(ProfileItemCrudController::class, 'Profile Items', 'fa-solid fa-list-ol'),
            MenuItem::linkTo(SavedSearchCrudController::class, 'Saved Searches', 'fa-solid fa-bookmark'),
            MenuItem::linkTo(SavedSearchAutomationCrudController::class, 'Saved Search Automations', 'fa-solid fa-robot'),
            MenuItem::linkTo(AutomationRunCrudController::class, 'Automation Runs', 'fa-solid fa-play'),
        ]);

        yield MenuItem::subMenu('Workflows', 'fa-solid fa-diagram-project')->setSubItems([
            MenuItem::linkTo(WorkflowStateCrudController::class, 'Workflow States', 'fa-solid fa-diagram-project'),
            MenuItem::linkTo(JobStateCrudController::class, 'Job States', 'fa-solid fa-gears'),
        ]);

        yield MenuItem::subMenu('Integrations', 'fa-solid fa-plug')->setSubItems([
            MenuItem::linkTo(WorkspaceIntegrationCrudController::class, 'Integrations', 'fa-solid fa-puzzle-piece'),
            MenuItem::linkTo(IntegrationDataCrudController::class, 'Integration Data', 'fa-solid fa-database'),
            MenuItem::linkTo(IntegrationTokenCrudController::class, 'Integration Tokens', 'fa-solid fa-key'),
            MenuItem::linkTo(WorkspaceEnvCrudController::class, 'Env Vars', 'fa-solid fa-terminal'),
            MenuItem::linkTo(WorkspaceSecretCrudController::class, 'Secrets', 'fa-solid fa-lock'),
            MenuItem::linkToRoute('Help', 'fa-solid fa-circle-question', 'admin_integrations_help'),
            MenuItem::section('Webhooks'),
            MenuItem::linkTo(WebhookCrudController::class, 'Webhooks', 'fa-solid fa-satellite-dish'),
            MenuItem::linkTo(WebhookLogCrudController::class, 'Webhook Errors', 'fa-solid fa-triangle-exclamation'),
        ]);

        yield MenuItem::subMenu('Logs', 'fa-solid fa-clock-rotate-left')->setSubItems([
            MenuItem::linkTo(AuditLogCrudController::class, 'Audit Log', 'fa-solid fa-user-clock'),
            MenuItem::linkTo(ChangeLogCrudController::class, 'Change Log', 'fa-solid fa-code-compare'),
        ]);

        yield MenuItem::subMenu('Maintenance', 'fa-solid fa-screwdriver-wrench')->setSubItems([
            MenuItem::linkTo(OperationTaskCrudController::class, 'Operation Tasks', 'fa-solid fa-hourglass-half'),
            MenuItem::linkTo(PopulatePassCrudController::class, 'Populate Passes', 'fa-solid fa-arrows-rotate'),
            ESAdminMenu::createMenuItem(),
        ]);

        yield NotifierAdminMenu::createMenuItem();
        yield $this->createSettingsMenu();
        yield $this->createDevMenu();
    }
}
