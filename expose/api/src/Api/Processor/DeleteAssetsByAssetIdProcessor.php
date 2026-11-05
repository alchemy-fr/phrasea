<?php

declare(strict_types=1);

namespace App\Api\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Asset;
use App\Security\Voter\AssetVoter;
use App\Storage\AssetManager;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

final readonly class DeleteAssetsByAssetIdProcessor implements ProcessorInterface
{
    public function __construct(
        private AssetManager $assetManager,
        private Security $security,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): null
    {
        /** @var Request $request */
        $request = $context['request'];

        $this->assetManager->deleteByAssetId(
            (string) $request->attributes->get('assetId'),
            function (Asset $asset): void {
                if (!$this->security->isGranted(AssetVoter::DELETE, $asset)) {
                    throw new AccessDeniedHttpException(sprintf('Cannot delete asset "%s"', $asset->getId()));
                }
            },
        );

        return null;
    }
}
