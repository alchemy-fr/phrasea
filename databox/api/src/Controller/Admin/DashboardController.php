<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use Alchemy\AdminBundle\Controller\AbstractAdminDashboardController;
use Alchemy\AdminBundle\Controller\Acl\AccessControlEntryCrudController;
use Alchemy\AdminBundle\Controller\MultipartUploadCrudController;
use Alchemy\AdminBundle\Home\HomeActivityItem;
use Alchemy\AdminBundle\Home\HomeChart;
use Alchemy\AdminBundle\Home\HomeStat;
use Alchemy\AdminBundle\Home\PeriodCounter;
use Alchemy\CoreBundle\Mapping\ObjectMapping;
use Alchemy\ESBundle\Admin\ESAdminMenu;
use Alchemy\NotifierBundle\Admin\NotifierAdminMenu;
use Alchemy\TrackBundle\AlchemyTrackBundle;
use Alchemy\TrackBundle\Controller\ChangeLogCrudController;
use Alchemy\WebhookBundle\Controller\WebhookCrudController;
use Alchemy\WebhookBundle\Controller\WebhookLogCrudController;
use Alchemy\Workflow\State\WorkflowState as ModelWorkflowState;
use App\Admin\Home\AssetHomeCharts;
use App\Entity\Basket\Basket;
use App\Entity\Core\Asset;
use App\Entity\Core\AssetExport;
use App\Entity\Core\AssetStatusEnum;
use App\Entity\Core\Collection;
use App\Entity\Core\Share;
use App\Entity\Core\Workspace;
use App\Entity\Log\AuditLog;
use App\Entity\Workflow\WorkflowState;
use App\Model\ExportStatusEnum;
use App\Service\Asset\Attribute\AssetNameResolver;
use App\Service\Asset\Attribute\AttributesResolver;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminDashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\MenuItem;
use Elastica\Index;
use Elastica\Query;
use FOS\ElasticaBundle\Finder\PaginatedFinderInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[AdminDashboard(routePath: '/admin', routeName: 'easyadmin')]
class DashboardController extends AbstractAdminDashboardController
{
    private const int HOME_BLOCK_SIZE = 10;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly Index $assetIndex,
        #[Autowire(service: 'fos_elastica.finder.asset')]
        private readonly PaginatedFinderInterface $assetFinder,
        private readonly AssetNameResolver $assetNameResolver,
        private readonly AttributesResolver $attributesResolver,
        #[Autowire(service: AlchemyTrackBundle::OBJECT_MAPPING_SERVICE_ID)]
        private readonly ObjectMapping $objectMapping,
        private readonly AssetHomeCharts $assetHomeCharts,
    ) {
    }

    #[\Override]
    public function configureMenuItems(): iterable
    {
        yield $this->createHomeMenuItem();

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
            MenuItem::linkTo(CollectionAssetCrudController::class, 'Collection Assets', 'fa-solid fa-folder-tree'),
            MenuItem::linkTo(AssetCrudController::class, 'Assets', 'fa-solid fa-image'),
            MenuItem::linkTo(AssetRelationshipCrudController::class, 'Asset Relationships', 'fa-solid fa-diagram-next'),
            MenuItem::linkTo(AssetFileVersionCrudController::class, 'Asset File Versions', 'fa-solid fa-code-branch'),
            MenuItem::linkTo(AssetImportCrudController::class, 'Asset Imports', 'fa-solid fa-file-import'),
            MenuItem::linkTo(AssetImportErrorCrudController::class, 'Asset Import Errors', 'fa-solid fa-circle-exclamation'),
            MenuItem::linkTo(FileCrudController::class, 'Files', 'fa-solid fa-file'),
            MenuItem::linkTo(FileAnalysisCrudController::class, 'File Analyses', 'fa-solid fa-microscope'),
            MenuItem::linkTo(FileDuplicateCrudController::class, 'File Duplicates', 'fa-solid fa-copy'),
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
            MenuItem::linkTo(MessageCrudController::class, 'Messages', 'fa-solid fa-message'),
            MenuItem::linkTo(PageCrudController::class, 'Pages', 'fa-solid fa-newspaper'),
            MenuItem::linkTo(TermsVersionCrudController::class, 'Terms Versions', 'fa-solid fa-file-contract'),
            MenuItem::linkTo(TermsSignatureCrudController::class, 'Terms Signatures', 'fa-solid fa-signature'),
        ]);

        yield MenuItem::subMenu('Searches', 'fa-solid fa-magnifying-glass')->setSubItems([
            MenuItem::linkTo(ProfileCrudController::class, 'Profiles', 'fa-solid fa-address-card'),
            MenuItem::linkTo(ProfileItemCrudController::class, 'Profile Items', 'fa-solid fa-list-ol'),
            MenuItem::linkTo(SavedSearchCrudController::class, 'Saved Searches', 'fa-solid fa-bookmark'),
            MenuItem::linkTo(SavedSearchAutomationCrudController::class, 'Saved Search Automations', 'fa-solid fa-robot'),
            MenuItem::linkTo(AutomationRunCrudController::class, 'Automation Runs', 'fa-solid fa-play'),
            MenuItem::linkTo(AutomationShareCrudController::class, 'Automation Shares', 'fa-solid fa-share-from-square'),
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

    #[\Override]
    protected function getHomeStats(): iterable
    {
        yield $this->createHomeStat(
            'Assets',
            'fa-solid fa-image',
            fn (): array => PeriodCounter::fromCallable(fn (?\DateTimeImmutable $since): int => $this->assetIndex->count($this->createAssetQuery($since))),
            $this->generateCrudUrl(AssetCrudController::class),
        );
        yield $this->createHomeStat(
            'Collections',
            'fa-solid fa-folder',
            fn (): array => PeriodCounter::fromQueryBuilder($this->em->getRepository(Collection::class)->createQueryBuilder('c')
                ->andWhere('c.deletedAt IS NULL'), 'c.createdAt'),
            $this->generateCrudUrl(CollectionCrudController::class),
        );
        yield $this->createHomeStat(
            'Workspaces',
            'fa-solid fa-briefcase',
            fn (): array => ['total' => $this->em->getRepository(Workspace::class)->count(['deletedAt' => null])],
            $this->generateCrudUrl(WorkspaceCrudController::class),
        );
        yield $this->createHomeStat(
            'Workflows',
            'fa-solid fa-diagram-project',
            fn (): array => PeriodCounter::fromQueryBuilder($this->em->getRepository(WorkflowState::class)->createQueryBuilder('w'), 'w.startedAt'),
            $this->generateCrudUrl(WorkflowStateCrudController::class),
        );
        yield $this->createHomeStat(
            'Failed workflows',
            'fa-solid fa-circle-xmark',
            fn (): array => PeriodCounter::fromQueryBuilder($this->em->getRepository(WorkflowState::class)->createQueryBuilder('w')
                ->andWhere('w.status = :status')
                ->setParameter('status', ModelWorkflowState::STATUS_FAILURE), 'w.startedAt'),
            $this->generateCrudUrl(WorkflowStateCrudController::class),
        );
        yield $this->createHomeStat(
            'Exports',
            'fa-solid fa-file-export',
            fn (): array => PeriodCounter::fromQueryBuilder($this->em->getRepository(AssetExport::class)->createQueryBuilder('e'), 'e.createdAt'),
            $this->generateCrudUrl(AssetExportCrudController::class),
        );
        yield $this->createHomeStat(
            'Shares',
            'fa-solid fa-share-nodes',
            fn (): array => PeriodCounter::fromQueryBuilder($this->em->getRepository(Share::class)->createQueryBuilder('s'), 's.createdAt'),
            $this->generateCrudUrl(ShareCrudController::class),
        );
        yield $this->createHomeStat(
            'Baskets',
            'fa-solid fa-basket-shopping',
            fn (): array => PeriodCounter::fromQueryBuilder($this->em->getRepository(Basket::class)->createQueryBuilder('b'), 'b.createdAt'),
            $this->generateCrudUrl(BasketCrudController::class),
        );
        // DQL has no COUNT(DISTINCT CASE …): one query per window
        yield $this->createHomeStat(
            'Active users',
            'fa-solid fa-users',
            fn (): array => PeriodCounter::fromCallable(function (?\DateTimeImmutable $since): int {
                $qb = $this->em->getRepository(AuditLog::class)->createQueryBuilder('a')
                    ->select('COUNT(DISTINCT a.userId)');
                if (null !== $since) {
                    $qb->andWhere('a.date >= :since')->setParameter('since', $since);
                }

                return (int) $qb->getQuery()->getSingleScalarResult();
            }),
            $this->generateCrudUrl(AuditLogCrudController::class),
        );
        yield fn (): ?HomeStat => $this->createFailedMessagesStat($this->em);
    }

    #[\Override]
    protected function getHomeCharts(): iterable
    {
        $assetsUrl = $this->generateCrudUrl(AssetCrudController::class);
        $workspacesUrl = $this->generateCrudUrl(WorkspaceCrudController::class);

        yield $this->createHomeChart(
            AssetHomeCharts::TIMELINE_TITLE,
            AssetHomeCharts::TIMELINE_ICON,
            fn (): HomeChart => $this->assetHomeCharts->getTimelineChart($assetsUrl),
            $assetsUrl,
        );
        yield $this->createHomeChart(
            AssetHomeCharts::FAMILY_TITLE,
            AssetHomeCharts::FAMILY_ICON,
            fn (): HomeChart => $this->assetHomeCharts->getFamilyChart($assetsUrl),
            $assetsUrl,
        );
        yield $this->createHomeChart(
            AssetHomeCharts::WORKSPACE_TITLE,
            AssetHomeCharts::WORKSPACE_ICON,
            fn (): HomeChart => $this->assetHomeCharts->getWorkspaceChart($workspacesUrl),
            $workspacesUrl,
        );
    }

    #[\Override]
    protected function getHomeActivityBlocks(): iterable
    {
        yield $this->createHomeActivityBlock(
            'Latest assets',
            'fa-solid fa-image',
            function (): iterable {
                $query = $this->createAssetQuery(null);
                $query->setSort(['createdAt' => ['order' => 'desc']]);
                $query->setSize(self::HOME_BLOCK_SIZE);

                /** @var Asset[] $assets */
                $assets = $this->assetFinder->find($query, self::HOME_BLOCK_SIZE);
                foreach ($assets as $asset) {
                    $status = $asset->getStatus();

                    yield new HomeActivityItem(
                        date: $asset->getCreatedAt(),
                        label: $this->resolveAssetName($asset) ?? $asset->getId(),
                        detail: $asset->getWorkspace()?->getName(),
                        url: $this->generateCrudUrl(AssetCrudController::class, Action::DETAIL, $asset->getId()),
                        userId: $asset->getOwnerId(),
                        status: $status->name,
                        statusVariant: AssetStatusEnum::Accepted === $status ? HomeActivityItem::VARIANT_SUCCESS : HomeActivityItem::VARIANT_WARNING,
                    );
                }
            },
            $this->generateCrudUrl(AssetCrudController::class),
        );

        yield $this->createHomeActivityBlock(
            'Audit log',
            'fa-solid fa-user-clock',
            function (): iterable {
                /** @var AuditLog[] $logs */
                $logs = $this->em->getRepository(AuditLog::class)->createQueryBuilder('a')
                    ->orderBy('a.date', 'DESC')
                    ->setMaxResults(self::HOME_BLOCK_SIZE)
                    ->getQuery()
                    ->getResult();

                foreach ($logs as $log) {
                    yield new HomeActivityItem(
                        date: $log->getDate(),
                        label: ucfirst(str_replace('_', ' ', $log->getAction()->getKey())),
                        detail: $log->getObjectLabel() ?? $this->getAuditLogObjectDescription($log),
                        url: $this->generateCrudUrl(AuditLogCrudController::class, Action::DETAIL, $log->getId()),
                        userId: $log->getUserId(),
                        userName: $log->getUserName(),
                    );
                }
            },
            $this->generateCrudUrl(AuditLogCrudController::class),
        );

        yield $this->createHomeActivityBlock(
            'Workflows',
            'fa-solid fa-diagram-project',
            function (): iterable {
                /** @var WorkflowState[] $workflows */
                $workflows = $this->em->getRepository(WorkflowState::class)->createQueryBuilder('w')
                    ->orderBy('w.startedAt', 'DESC')
                    ->setMaxResults(self::HOME_BLOCK_SIZE)
                    ->getQuery()
                    ->getResult();

                foreach ($workflows as $workflow) {
                    [$status, $variant] = match ($workflow->getStatus()) {
                        ModelWorkflowState::STATUS_STARTED => ['Started', HomeActivityItem::VARIANT_INFO],
                        ModelWorkflowState::STATUS_SUCCESS => ['Success', HomeActivityItem::VARIANT_SUCCESS],
                        ModelWorkflowState::STATUS_FAILURE => ['Failure', HomeActivityItem::VARIANT_DANGER],
                        ModelWorkflowState::STATUS_CANCELLED => ['Cancelled', HomeActivityItem::VARIANT_SECONDARY],
                        default => [(string) $workflow->getStatus(), HomeActivityItem::VARIANT_SECONDARY],
                    };
                    $asset = $workflow->getAsset();

                    yield new HomeActivityItem(
                        date: $workflow->getStartedAt(),
                        label: $workflow->getName(),
                        detail: null !== $asset ? $this->resolveAssetName($asset) : null,
                        url: $this->generateCrudUrl(WorkflowStateCrudController::class, Action::DETAIL, $workflow->getId()),
                        userId: $workflow->getInitiatorId(),
                        status: $status,
                        statusVariant: $variant,
                    );
                }
            },
            $this->generateCrudUrl(WorkflowStateCrudController::class),
        );

        yield $this->createHomeActivityBlock(
            'Exports',
            'fa-solid fa-file-export',
            function (): iterable {
                /** @var AssetExport[] $exports */
                $exports = $this->em->getRepository(AssetExport::class)->createQueryBuilder('e')
                    ->orderBy('e.createdAt', 'DESC')
                    ->setMaxResults(self::HOME_BLOCK_SIZE)
                    ->getQuery()
                    ->getResult();

                foreach ($exports as $export) {
                    $status = $export->getStatus();
                    $assetCount = count($export->getAssets() ?? []) + count($export->getBasketAssets() ?? []);
                    $renditions = $export->getRenditions();

                    yield new HomeActivityItem(
                        date: $export->getCreatedAt(),
                        label: sprintf('%d asset%s', $assetCount, 1 === $assetCount ? '' : 's'),
                        detail: null !== $renditions && [] !== $renditions ? sprintf('%d rendition%s', count($renditions), 1 === count($renditions) ? '' : 's') : null,
                        url: $this->generateCrudUrl(AssetExportCrudController::class, Action::DETAIL, $export->getId()),
                        userId: $export->getOwnerId(),
                        status: $status->name,
                        statusVariant: match ($status) {
                            ExportStatusEnum::Pending => HomeActivityItem::VARIANT_SECONDARY,
                            ExportStatusEnum::InProgress => HomeActivityItem::VARIANT_INFO,
                            ExportStatusEnum::Ready => HomeActivityItem::VARIANT_SUCCESS,
                            ExportStatusEnum::Failed => HomeActivityItem::VARIANT_DANGER,
                        },
                    );
                }
            },
            $this->generateCrudUrl(AssetExportCrudController::class),
        );
    }

    /**
     * The non-deleted assets, created since the given date if any: assets are counted and
     * listed through Elasticsearch only, never in SQL.
     */
    private function createAssetQuery(?\DateTimeImmutable $since): Query
    {
        $boolQuery = new Query\BoolQuery();
        $boolQuery->addFilter(new Query\Term(['deleted' => false]));
        if (null !== $since) {
            $boolQuery->addFilter(new Query\Range('createdAt', ['gte' => $since->format(\DateTimeInterface::ATOM)]));
        }

        return new Query($boolQuery);
    }

    private function resolveAssetName(Asset $asset): ?string
    {
        try {
            return $this->assetNameResolver->resolveNameAsString($asset, $this->attributesResolver->resolveAssetAttributes($asset, false));
        } catch (\Throwable) {
            return $asset->getSource()?->getOriginalName();
        }
    }

    private function getAuditLogObjectDescription(AuditLog $log): ?string
    {
        if (null === $objectType = $log->getObjectType()) {
            return $log->getObjectId();
        }

        try {
            $type = (new \ReflectionClass($this->objectMapping->getClassName($objectType)))->getShortName();
        } catch (\Throwable) {
            $type = (string) $objectType;
        }

        return trim($type.' '.$log->getObjectId());
    }
}
