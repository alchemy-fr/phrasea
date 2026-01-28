<?php

declare(strict_types=1);

namespace App\Api\Provider;

use Alchemy\AuthBundle\Security\Traits\SecurityAwareTrait;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Entity\Core\Asset;
use App\Repository\Core\AssetRepository;
use App\Security\Voter\AbstractVoter;
use App\Service\Matomo\MatomoManager;
use Symfony\Component\HttpFoundation\JsonResponse;

class AssetMetricsProvider implements ProviderInterface
{
    use SecurityAwareTrait;

    public function __construct(
        private readonly MatomoManager $matomoManager,
        private readonly AssetRepository $assetRepository,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        $asset = $this->assetRepository->find($uriVariables['id']);
        if (!$asset instanceof Asset) {
            return null;
        }

        $this->denyAccessUnlessGranted(AbstractVoter::READ, $asset);

        $filters = $context['filters'] ?? [];

        return new JsonResponse($this
            ->matomoManager
            ->getMediaMetrics($asset->getTrackingId() ?? $asset->getId(), $filters['type'] ?? ''));
    }
}
