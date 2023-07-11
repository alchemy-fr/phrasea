<?php

declare(strict_types=1);

namespace App\Api\DataProvider;

use ApiPlatform\Core\DataProvider\ContextAwareCollectionDataProviderInterface;
use ApiPlatform\Core\DataProvider\RestrictedDataProviderInterface;
use App\Entity\Core\Asset;
use App\Security\Voter\AssetVoter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Bundle\SecurityBundle\Security;

abstract class AbstractAssetFilteredCollectionDataProvider implements ContextAwareCollectionDataProviderInterface, RestrictedDataProviderInterface
{
    public function __construct(protected EntityManagerInterface $em, protected Security $security)
    {
    }

    protected function getAsset(array $context): Asset
    {
        $filters = $context['filters'] ?? [];
        if (!isset($filters['assetId'])) {
            throw new BadRequestHttpException('You must provide "assetId" to filter out results');
        }

        $asset = $this->em->find(Asset::class, $filters['assetId']);
        if (!$asset instanceof Asset) {
            throw new NotFoundHttpException(sprintf('Asset "%s" does not exist', $asset));
        }

        if (!$this->security->isGranted(AssetVoter::READ, $asset)) {
            throw new AccessDeniedHttpException('Cannot read asset');
        }

        return $asset;
    }
}
