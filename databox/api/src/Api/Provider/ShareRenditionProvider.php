<?php

declare(strict_types=1);

namespace App\Api\Provider;

use Alchemy\AuthBundle\Security\Traits\SecurityAwareTrait;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\UrlGeneratorInterface;
use ApiPlatform\State\ProviderInterface;
use App\Api\Traits\ItemProviderAwareTrait;
use App\Asset\FileUrlResolver;
use App\Entity\Core\AssetRendition;
use App\Entity\Core\Share;
use App\Repository\Core\ShareRepository;
use App\Security\RenditionPermissionManager;
use App\Security\Voter\AbstractVoter;
use App\Storage\RenditionManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

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
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        $item = $this->shareRepository->find($uriVariables['id']);
        if (!$item instanceof Share) {
            throw new NotFoundHttpException('Share not found');
        }

        $this->denyAccessUnlessGranted(AbstractVoter::READ, $item);

        $defId = $uriVariables['rendition'];
        $rendition = $this->em->getRepository(AssetRendition::class)->findOneBy([
            'asset' => $item->getAsset()->getId(),
            'definition' => $defId,
        ], [
            'createdAt' => 'DESC',
        ]);

        if (null !== $file = $rendition?->getFile()) {
            return new RedirectResponse($this->fileUrlResolver->resolveUrl($file));
        }

        throw new NotFoundHttpException('Rendition not found');
    }
}
