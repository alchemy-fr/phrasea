<?php

declare(strict_types=1);

namespace App\Api\Provider;

use Alchemy\AuthBundle\Security\Traits\SecurityAwareTrait;
use ApiPlatform\Metadata\Operation;
use App\Api\Traits\ParameterValuesTrait;
use App\Entity\Core\Asset;
use App\Security\Voter\AbstractVoter;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

abstract class AbstractAssetFilteredCollectionProvider extends AbstractCollectionProvider
{
    use ParameterValuesTrait;
    use SecurityAwareTrait;

    /**
     * The asset of the "assetId" parameter (or "asset", as an ID or IRI), which is mandatory.
     */
    protected function getAsset(Operation $operation): Asset
    {
        $assetId = self::getParameterId($operation, 'assetId') ?? self::getParameterId($operation, 'asset')
            ?? throw new BadRequestHttpException('You must provide "assetId" to filter out results');

        $asset = $this->em->find(Asset::class, $assetId);
        if (!$asset instanceof Asset) {
            throw new NotFoundHttpException(sprintf('Asset "%s" does not exist', $assetId));
        }

        if (!$this->security->isGranted(AbstractVoter::READ, $asset)) {
            throw new AccessDeniedHttpException('Cannot read asset');
        }

        return $asset;
    }
}
