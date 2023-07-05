<?php

namespace App\Controller\Admin;

use Alchemy\AclBundle\Entity\AccessControlEntry;
use Alchemy\AdminBundle\Controller\AbstractAdminDashboardController;
use Alchemy\OAuthServerBundle\Entity\AccessToken;
use Alchemy\OAuthServerBundle\Entity\OAuthClient;
use Alchemy\WebhookBundle\Entity\Webhook;
use Alchemy\WebhookBundle\Entity\WebhookLog;
use Alchemy\Workflow\Doctrine\Entity\JobState;
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
use App\Entity\Integration\WorkspaceSecret;
use App\Entity\Template\AssetDataTemplate;
use App\Entity\Template\TemplateAttribute;
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

        return $this->redirect($adminUrlGenerator->setController(WebhookCrudController::class)->generateUrl());
    }

    public function configureMenuItems(): iterable
    {
        $submenu1 = [
            MenuItem::linkToRoute('Asset permissions', '', 'alchemy_admin_acl_global_permissions', ['type' => 'asset']),
            MenuItem::linkToRoute('Collection permissions', '', 'alchemy_admin_acl_global_permissions', ['type' => 'collection']),
            MenuItem::linkToRoute('Workspace permissions', '', 'alchemy_admin_acl_global_permissions', ['type' => 'workspace']),
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

        $submenuTemplates = [
            MenuItem::linkToCrud('AssetDataTemplate', '', AssetDataTemplate::class),
            MenuItem::linkToCrud('TemplateAttribute', '', TemplateAttribute::class),
        ];

        $submenu3 = [
            MenuItem::linkToCrud('PopulatePass', '', PopulatePass::class),
            MenuItem::linkToCrud('ESIndexState', '', ESIndexState::class),
        ];

        $submenu4 = [
            MenuItem::linkToCrud('Integration', '', WorkspaceIntegration::class),
            MenuItem::linkToCrud('Secrets', '', WorkspaceSecret::class),
            MenuItem::linkToRoute('Help', '', 'admin_integrations_help'),
        ];

        $submenu6 = [
            MenuItem::linkToCrud('Webhooks', '', Webhook::class),
            MenuItem::linkToCrud('Webhook errors', '', WebhookLog::class),
        ];

        $workflows = [
            MenuItem::linkToCrud('Workflows states', '', WorkflowState::class),
            MenuItem::linkToCrud('Job states', '', JobState::class),
        ];

        yield MenuItem::subMenu('Permissions', 'fas fa-folder-open')->setSubItems($submenu1);
        yield MenuItem::subMenu('Core', 'fas fa-folder-open')->setSubItems($submenu2);
        yield MenuItem::subMenu('Admin', 'fas fa-folder-open')->setSubItems($submenu3);
        yield MenuItem::linkToCrud('OAuthClient', 'fas fa-folder-open', OAuthClient::class);
        yield MenuItem::subMenu('Templates', 'fas fa-folder-open')->setSubItems($submenuTemplates);
        yield MenuItem::subMenu('Integrations', 'fas fa-folder-open')->setSubItems($submenu4);
        yield MenuItem::subMenu('Workflows', 'fas fa-folder-open')->setSubItems($workflows);
        yield $this->createDevMenu(FailedEvent::class);
        yield MenuItem::subMenu('Webhooks', 'fas fa-folder-open')->setSubItems($submenu6);
    }
}
