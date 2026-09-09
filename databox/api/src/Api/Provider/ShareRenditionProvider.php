<?php

declare(strict_types=1);

namespace App\Api\Provider;

use Alchemy\AuthBundle\Security\Traits\SecurityAwareTrait;
use Alchemy\MessengerBundle\Listener\TerminateStackListener;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Entity\Core\Asset;
use App\Entity\Core\AssetRendition;
use App\Entity\Core\Share;
use App\Repository\Core\ShareRepository;
use App\Security\Voter\AbstractVoter;
use App\Service\Asset\Attribute\AssetNameResolver;
use App\Service\Asset\FileUrlResolver;
use App\Service\Storage\RenditionManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;

final class ShareRenditionProvider implements ProviderInterface
{
    use SecurityAwareTrait;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly RenditionManager $renditionManager,
        private readonly FileUrlResolver $fileUrlResolver,
        private readonly ShareRepository $shareRepository,
        private readonly TerminateStackListener $terminateStackListener,
        private readonly AssetNameResolver $assetNameResolver,
        private readonly RequestStack $requestStack,
        private string $matomoSiteId,
        #[Autowire(env: 'MATOMO_URL')]
        private string $matomoUrl,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object
    {
        $item = $this->shareRepository->find($uriVariables['id']);
        if (!$item instanceof Share) {
            return $this->createNotFoundResponse();
        }

        if (!$this->security->isGranted(AbstractVoter::READ, $item)) {
            return $this->createNotFoundResponse();
        }

        $asset = $this->resolveAsset($item);
        if (null === $asset) {
            return $this->createNotFoundResponse();
        }

        $defId = $uriVariables['rendition'];
        $rendition = $this->em->getRepository(AssetRendition::class)->findOneBy([
            'asset' => $asset->getId(),
            'definition' => $defId,
        ], [
            'createdAt' => 'DESC',
        ]);

        if (null !== $file = $rendition?->getFile()) {
            $matomoTracker = new \MatomoTracker((int) $this->matomoSiteId, $this->matomoUrl);
            $trackingId = $asset->getResolvedTrackingId();
            $name = $this->assetNameResolver->resolveNameAsString($asset);

            $this->terminateStackListener->addCallback(function () use ($matomoTracker, $name, $trackingId) {
                $matomoTracker->doTrackContentImpression($name, $trackingId);
            });

            return new RedirectResponse($this->fileUrlResolver->resolveUrl($file));
        }

        return $this->createNotFoundResponse();
    }

    private function resolveAsset(Share $share): ?Asset
    {
        $assetId = $this->requestStack->getCurrentRequest()?->query->get('asset');
        if (null === $assetId || '' === $assetId) {
            $first = $share->getAssets()->first();

            return $first instanceof Asset ? $first : null;
        }

        foreach ($share->getAssetsList() as $asset) {
            if ($asset->getId() === $assetId) {
                return $asset;
            }
        }

        return null;
    }

    private function createNotFoundResponse(): Response
    {
        return new Response('', Response::HTTP_NOT_FOUND);
    }
}
