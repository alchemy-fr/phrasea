<?php

declare(strict_types=1);

namespace App\Api\Provider;

use App\Entity\Core\Asset;
use App\Security\Voter\AbstractVoter;
use App\Util\SecurityAwareTrait;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

abstract class AbstractAssetFilteredCollectionDataProvider extends AbstractCollectionProvider
{
    use SecurityAwareTrait;

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

        if (!$this->security->isGranted(AbstractVoter::READ, $asset)) {
            throw new AccessDeniedHttpException('Cannot read asset');
        }

        return $asset;
    }
}
