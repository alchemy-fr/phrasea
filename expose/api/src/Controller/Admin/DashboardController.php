<?php

namespace App\Controller\Admin;

use Alchemy\AdminBundle\Controller\AbstractAdminDashboardController;
use Alchemy\AdminBundle\Controller\Acl\AccessControlEntryCrudController;
use Alchemy\AdminBundle\Controller\MultipartUploadCrudController;
use Alchemy\AdminBundle\Home\HomeActivityItem;
use Alchemy\AdminBundle\Home\HomeStat;
use Alchemy\AdminBundle\Home\PeriodCounter;
use Alchemy\NotifierBundle\Admin\NotifierAdminMenu;
use App\Entity\Asset;
use App\Entity\DownloadRequest;
use App\Entity\Publication;
use App\Entity\PublicationProfile;
use App\Entity\PublicationShare;
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
    ) {
    }

    public function configureMenuItems(): iterable
    {
        yield $this->createHomeMenuItem();

        yield MenuItem::subMenu('Permissions', 'fa-solid fa-shield-halved')->setSubItems([
            MenuItem::linkTo(PublicationCrudController::class, 'Publication permissions', 'fa-solid fa-newspaper')->setAction('globalPermissions'),
            MenuItem::linkTo(PublicationProfileCrudController::class, 'Profile permissions', 'fa-solid fa-id-card')->setAction('globalPermissions'),
            MenuItem::linkTo(AccessControlEntryCrudController::class, 'All permissions (advanced)', 'fa-solid fa-user-shield'),
        ]);

        yield MenuItem::subMenu('Publications', 'fa-solid fa-newspaper')->setSubItems([
            MenuItem::linkTo(PublicationCrudController::class, 'Publications', 'fa-solid fa-newspaper'),
            MenuItem::linkTo(PublicationProfileCrudController::class, 'Profiles', 'fa-solid fa-id-card'),
            MenuItem::linkTo(AssetCrudController::class, 'Assets', 'fa-solid fa-image'),
            MenuItem::linkTo(SubDefinitionCrudController::class, 'Sub-definitions', 'fa-solid fa-images'),
            MenuItem::linkTo(MultipartUploadCrudController::class, 'Multipart Uploads', 'fa-solid fa-upload'),
        ]);

        yield NotifierAdminMenu::createMenuItem();
        yield $this->createSettingsMenu([
            MenuItem::linkTo(EnvVarCrudController::class, 'Env Vars', 'fa-solid fa-terminal'),
        ]);
        yield $this->createDevMenu();
    }

    protected function getHomeStats(): iterable
    {
        yield $this->createHomeStat(
            'Publications',
            'fa-solid fa-newspaper',
            fn (): array => PeriodCounter::fromQueryBuilder($this->em->getRepository(Publication::class)->createQueryBuilder('p'), 'p.createdAt'),
            $this->generateCrudUrl(PublicationCrudController::class),
        );
        yield $this->createHomeStat(
            'Assets',
            'fa-solid fa-image',
            fn (): array => PeriodCounter::fromQueryBuilder($this->em->getRepository(Asset::class)->createQueryBuilder('a'), 'a.createdAt'),
            $this->generateCrudUrl(AssetCrudController::class),
        );
        yield $this->createHomeStat(
            'Download requests',
            'fa-solid fa-download',
            fn (): array => PeriodCounter::fromQueryBuilder($this->em->getRepository(DownloadRequest::class)->createQueryBuilder('d'), 'd.createdAt'),
        );
        yield $this->createHomeStat(
            'Publication shares',
            'fa-solid fa-share-nodes',
            fn (): array => PeriodCounter::fromQueryBuilder($this->em->getRepository(PublicationShare::class)->createQueryBuilder('s'), 's.createdAt'),
        );
        yield $this->createHomeStat(
            'Profiles',
            'fa-solid fa-id-card',
            fn (): array => ['total' => $this->em->getRepository(PublicationProfile::class)->count()],
            $this->generateCrudUrl(PublicationProfileCrudController::class),
        );
        yield fn (): ?HomeStat => $this->createFailedMessagesStat($this->em);
    }

    protected function getHomeActivityBlocks(): iterable
    {
        yield $this->createHomeActivityBlock(
            'Latest publications',
            'fa-solid fa-newspaper',
            function (): iterable {
                /** @var Publication[] $publications */
                $publications = $this->em->getRepository(Publication::class)->createQueryBuilder('p')
                    ->addSelect('pr')
                    ->leftJoin('p.profile', 'pr')
                    ->orderBy('p.createdAt', 'DESC')
                    ->setMaxResults(self::HOME_BLOCK_SIZE)
                    ->getQuery()
                    ->getResult();

                foreach ($publications as $publication) {
                    $enabled = $publication->isEnabled();

                    yield new HomeActivityItem(
                        date: $publication->getCreatedAt(),
                        label: $publication->getTitle() ?? $publication->getId(),
                        detail: $publication->getProfile()?->getName(),
                        url: $this->generateCrudUrl(PublicationCrudController::class, Action::DETAIL, $publication->getId()),
                        userId: $publication->getOwnerId(),
                        status: $enabled ? 'Enabled' : 'Disabled',
                        statusVariant: $enabled ? HomeActivityItem::VARIANT_SUCCESS : HomeActivityItem::VARIANT_SECONDARY,
                    );
                }
            },
            $this->generateCrudUrl(PublicationCrudController::class),
        );

        yield $this->createHomeActivityBlock(
            'Latest assets',
            'fa-solid fa-image',
            function (): iterable {
                /** @var Asset[] $assets */
                $assets = $this->em->getRepository(Asset::class)->createQueryBuilder('a')
                    ->addSelect('p')
                    ->leftJoin('a.publication', 'p')
                    ->orderBy('a.createdAt', 'DESC')
                    ->setMaxResults(self::HOME_BLOCK_SIZE)
                    ->getQuery()
                    ->getResult();

                foreach ($assets as $asset) {
                    $title = $asset->getTitle();

                    yield new HomeActivityItem(
                        date: $asset->getCreatedAt(),
                        label: null !== $title && '' !== $title ? $title : $asset->getOriginalName(),
                        detail: $asset->getPublication()->getTitle(),
                        url: $this->generateCrudUrl(AssetCrudController::class, Action::DETAIL, $asset->getId()),
                        userId: $asset->getOwnerId(),
                    );
                }
            },
            $this->generateCrudUrl(AssetCrudController::class),
        );

        yield $this->createHomeActivityBlock(
            'Latest download requests',
            'fa-solid fa-download',
            function (): iterable {
                /** @var DownloadRequest[] $requests */
                $requests = $this->em->getRepository(DownloadRequest::class)->createQueryBuilder('d')
                    ->addSelect('p', 'a')
                    ->leftJoin('d.publication', 'p')
                    ->leftJoin('d.asset', 'a')
                    ->orderBy('d.createdAt', 'DESC')
                    ->setMaxResults(self::HOME_BLOCK_SIZE)
                    ->getQuery()
                    ->getResult();

                foreach ($requests as $request) {
                    $asset = $request->getAsset();
                    $assetTitle = $asset?->getTitle();
                    if (null !== $asset && (null === $assetTitle || '' === $assetTitle)) {
                        $assetTitle = $asset->getOriginalName();
                    }

                    yield new HomeActivityItem(
                        date: $request->getCreatedAt(),
                        label: $request->getEmail() ?? $request->getId(),
                        detail: implode(' / ', array_filter([$request->getPublication()?->getTitle(), $assetTitle])),
                    );
                }
            },
        );
    }
}
