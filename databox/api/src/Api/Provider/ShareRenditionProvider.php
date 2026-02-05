<?php

declare(strict_types=1);

namespace App\Api\Provider;

use Alchemy\AuthBundle\Security\Traits\SecurityAwareTrait;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\UrlGeneratorInterface;
use ApiPlatform\State\ProviderInterface;
use App\Entity\Core\AssetRendition;
use App\Entity\Core\Share;
use App\Repository\Core\ShareRepository;
use App\Security\RenditionPermissionManager;
use App\Security\Voter\AbstractVoter;
use App\Service\Asset\FileUrlResolver;
use App\Service\Storage\RenditionManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;

final class ShareRenditionProvider implements ProviderInterface
{
    use SecurityAwareTrait;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly RenditionManager $renditionManager,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly RenditionPermissionManager $renditionPermissionManager,
        private readonly FileUrlResolver $fileUrlResolver,
        private readonly ShareRepository $shareRepository,
        private string $matomoSiteId,
        #[Autowire(env: 'string:MATOMO_URL')]
        private string $matomoUrl,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        $item = $this->shareRepository->find($uriVariables['id']);
        if (!$item instanceof Share) {
            return $this->createNotFoundResponse();
        }

        if (!$this->security->isGranted(AbstractVoter::READ, $item)) {
            return $this->createNotFoundResponse();
        }

        $defId = $uriVariables['rendition'];
        $rendition = $this->em->getRepository(AssetRendition::class)->findOneBy([
            'asset' => $item->getAsset()->getId(),
            'definition' => $defId,
        ], [
            'createdAt' => 'DESC',
        ]);

        if (null !== $file = $rendition?->getFile()) {
            $matomoTracker = new \MatomoTracker((int) $this->matomoSiteId, $this->matomoUrl);
            $asset = $item->getAsset();
            $trackingId = $asset->getTrackingId() ?? $asset->getId();

            $matomoTracker->doTrackContentImpression($asset->getTitle(), $trackingId);

            return new RedirectResponse($this->fileUrlResolver->resolveUrl($file));
        }

        return $this->createNotFoundResponse();
    }

    private function createNotFoundResponse(): Response
    {
        return new Response('', Response::HTTP_NOT_FOUND);
    }
}
