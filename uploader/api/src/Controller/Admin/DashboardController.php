<?php

namespace App\Controller\Admin;

use Alchemy\AdminBundle\Controller\AbstractAdminDashboardController;
use Alchemy\AdminBundle\Controller\Acl\AccessControlEntryCrudController;
use Alchemy\AdminBundle\Controller\MultipartUploadCrudController;
use Alchemy\AdminBundle\Home\HomeActivityItem;
use Alchemy\AdminBundle\Home\HomeChart;
use Alchemy\AdminBundle\Home\HomeStat;
use Alchemy\AdminBundle\Home\PeriodCounter;
use Alchemy\AdminBundle\Utils\SizeUtils;
use Alchemy\NotifierBundle\Admin\NotifierAdminMenu;
use App\Admin\Home\UploaderHomeCharts;
use App\Entity\Asset;
use App\Entity\Commit;
use App\Entity\Target;
use App\Entity\UploadLink;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminDashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\MenuItem;

#[AdminDashboard(routePath: '/admin', routeName: 'easyadmin')]
class DashboardController extends AbstractAdminDashboardController
{
    private const int HOME_BLOCK_SIZE = 10;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly UploaderHomeCharts $homeCharts,
    ) {
    }

    public function configureMenuItems(): iterable
    {
        yield $this->createHomeMenuItem();

        yield MenuItem::subMenu('Permissions', 'fa-solid fa-shield-halved')->setSubItems([
            MenuItem::linkToRoute('Target params permissions', 'fa-solid fa-sliders', GlobalPermissionsController::ROUTE_TARGET_DATA),
            MenuItem::linkToRoute('Form schema permissions', 'fa-solid fa-table-list', GlobalPermissionsController::ROUTE_FORM_SCHEMA),
            MenuItem::linkToRoute('Upload link permissions', 'fa-solid fa-link', GlobalPermissionsController::ROUTE_UPLOAD_LINK),
            MenuItem::linkTo(AccessControlEntryCrudController::class, 'All permissions (advanced)', 'fa-solid fa-user-shield'),
        ]);

        yield MenuItem::subMenu('Uploads', 'fa-solid fa-cloud-arrow-up')->setSubItems([
            MenuItem::linkTo(TargetCrudController::class, 'Targets', 'fa-solid fa-bullseye'),
            MenuItem::linkTo(UploadLinkCrudController::class, 'Upload Links', 'fa-solid fa-share-from-square'),
            MenuItem::linkTo(CommitCrudController::class, 'Commits', 'fa-solid fa-code-commit'),
            MenuItem::linkTo(AssetCrudController::class, 'Assets', 'fa-solid fa-image'),
            MenuItem::linkTo(MultipartUploadCrudController::class, 'Multipart Uploads', 'fa-solid fa-upload'),
        ]);

        yield NotifierAdminMenu::createMenuItem();
        yield $this->createSettingsMenu();
        yield $this->createDevMenu();
    }

    protected function getHomeStats(): iterable
    {
        yield $this->createHomeStat(
            'Commits',
            'fa-solid fa-code-commit',
            fn (): array => PeriodCounter::fromQueryBuilder($this->em->getRepository(Commit::class)->createQueryBuilder('c'), 'c.createdAt'),
            $this->generateCrudUrl(CommitCrudController::class),
        );
        yield $this->createHomeStat(
            'Uploaded assets',
            'fa-solid fa-image',
            fn (): array => PeriodCounter::fromQueryBuilder($this->em->getRepository(Asset::class)->createQueryBuilder('a'), 'a.createdAt'),
            $this->generateCrudUrl(AssetCrudController::class),
        );
        yield $this->createHomeStat(
            'Uploaded volume',
            'fa-solid fa-hard-drive',
            fn (): array => PeriodCounter::fromQueryBuilder($this->em->getRepository(Commit::class)->createQueryBuilder('c'), 'c.createdAt', 'c.totalSize'),
            $this->generateCrudUrl(CommitCrudController::class),
            HomeStat::FORMAT_BYTES,
        );
        yield $this->createHomeStat(
            'Unacknowledged commits',
            'fa-solid fa-hourglass-half',
            fn (): array => ['total' => $this->em->getRepository(Commit::class)->count(['acknowledged' => false])],
            $this->generateCrudUrl(CommitCrudController::class),
        );
        yield $this->createHomeStat(
            'Targets',
            'fa-solid fa-bullseye',
            fn (): array => ['total' => $this->em->getRepository(Target::class)->count()],
            $this->generateCrudUrl(TargetCrudController::class),
        );
        yield $this->createHomeStat(
            'Upload links',
            'fa-solid fa-share-from-square',
            fn (): array => ['total' => $this->em->getRepository(UploadLink::class)->count()],
            $this->generateCrudUrl(UploadLinkCrudController::class),
        );
        yield fn (): ?HomeStat => $this->createFailedMessagesStat($this->em);
    }

    #[\Override]
    protected function getHomeCharts(): iterable
    {
        $commitsUrl = $this->generateCrudUrl(CommitCrudController::class);
        $assetsUrl = $this->generateCrudUrl(AssetCrudController::class);
        $targetsUrl = $this->generateCrudUrl(TargetCrudController::class);

        yield $this->createHomeChart(
            UploaderHomeCharts::UPLOADS_TITLE,
            UploaderHomeCharts::UPLOADS_ICON,
            fn (): HomeChart => $this->homeCharts->getUploadsChart($commitsUrl),
            $commitsUrl,
            UploaderHomeCharts::UPLOADS_WIDTH,
        );
        yield $this->createHomeChart(
            UploaderHomeCharts::FILE_TYPE_TITLE,
            UploaderHomeCharts::FILE_TYPE_ICON,
            fn (): HomeChart => $this->homeCharts->getFileTypeChart($assetsUrl),
            $assetsUrl,
            UploaderHomeCharts::FILE_TYPE_WIDTH,
        );
        yield $this->createHomeChart(
            UploaderHomeCharts::TARGET_TITLE,
            UploaderHomeCharts::TARGET_ICON,
            fn (): HomeChart => $this->homeCharts->getTargetChart($targetsUrl),
            $targetsUrl,
            UploaderHomeCharts::TARGET_WIDTH,
        );
    }

    protected function getHomeActivityBlocks(): iterable
    {
        yield $this->createHomeActivityBlock(
            'Latest commits',
            'fa-solid fa-code-commit',
            function (): iterable {
                /** @var Commit[] $commits */
                $commits = $this->em->getRepository(Commit::class)->createQueryBuilder('c')
                    ->addSelect('t')
                    ->leftJoin('c.target', 't')
                    ->orderBy('c.createdAt', 'DESC')
                    ->setMaxResults(self::HOME_BLOCK_SIZE)
                    ->getQuery()
                    ->getResult();

                $assetCounts = $this->countCommitAssets($commits);
                foreach ($commits as $commit) {
                    $assetCount = $assetCounts[$commit->getId()] ?? 0;

                    yield new HomeActivityItem(
                        date: $commit->getCreatedAt(),
                        label: $commit->getTarget()?->getName() ?? $commit->getId(),
                        detail: sprintf('%d asset%s, %s', $assetCount, 1 === $assetCount ? '' : 's', SizeUtils::formatSize($commit->getTotalSize())),
                        url: $this->generateCrudUrl(CommitCrudController::class, Action::DETAIL, $commit->getId()),
                        userId: $commit->getUserId(),
                        status: $commit->isAcknowledged() ? 'Acknowledged' : 'Pending',
                        statusVariant: $commit->isAcknowledged() ? HomeActivityItem::VARIANT_SUCCESS : HomeActivityItem::VARIANT_WARNING,
                    );
                }
            },
            $this->generateCrudUrl(CommitCrudController::class),
        );

        yield $this->createHomeActivityBlock(
            'Latest upload links used',
            'fa-solid fa-share-from-square',
            function (): iterable {
                /** @var UploadLink[] $links */
                $links = $this->em->getRepository(UploadLink::class)->createQueryBuilder('l')
                    ->addSelect('t')
                    ->leftJoin('l.target', 't')
                    ->andWhere('l.lastCommitAt IS NOT NULL')
                    ->orderBy('l.lastCommitAt', 'DESC')
                    ->setMaxResults(self::HOME_BLOCK_SIZE)
                    ->getQuery()
                    ->getResult();

                foreach ($links as $link) {
                    yield new HomeActivityItem(
                        date: $link->getLastCommitAt(),
                        label: $link->getName() ?? $link->getId(),
                        detail: $link->getTarget()?->getName(),
                        url: $this->generateCrudUrl(UploadLinkCrudController::class, Action::DETAIL, $link->getId()),
                        userId: $link->getOwnerId(),
                        status: $link->isEnabled() ? 'Enabled' : 'Disabled',
                        statusVariant: $link->isEnabled() ? HomeActivityItem::VARIANT_SUCCESS : HomeActivityItem::VARIANT_SECONDARY,
                    );
                }
            },
            $this->generateCrudUrl(UploadLinkCrudController::class),
        );
    }

    /**
     * @param Commit[] $commits
     *
     * @return array<string, int> commit ID => number of assets
     */
    private function countCommitAssets(array $commits): array
    {
        if ([] === $commits) {
            return [];
        }

        $rows = $this->em->getRepository(Asset::class)->createQueryBuilder('a')
            ->select('IDENTITY(a.commit) AS commitId, COUNT(a.id) AS assetCount')
            ->andWhere('a.commit IN (:commits)')
            ->setParameter('commits', $commits)
            ->groupBy('a.commit')
            ->getQuery()
            ->getArrayResult();

        return array_column(array_map(static fn (array $row): array => [(string) $row['commitId'], (int) $row['assetCount']], $rows), 1, 0);
    }
}
