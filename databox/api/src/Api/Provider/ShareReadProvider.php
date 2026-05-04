<?php

declare(strict_types=1);

namespace App\Api\Provider;

use Alchemy\AuthBundle\Security\Traits\SecurityAwareTrait;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\UrlGeneratorInterface;
use ApiPlatform\State\ProviderInterface;
use App\Api\Model\Output\ShareAlternateUrlOutput;
use App\Api\Traits\ItemProviderAwareTrait;
use App\Entity\Core\AssetRendition;
use App\Entity\Core\Share;
use App\Repository\Core\AssetRenditionRepository;
use App\Security\Voter\AbstractVoter;
use App\Service\Storage\RenditionManager;
use Doctrine\ORM\EntityManagerInterface;

final class ShareReadProvider implements ProviderInterface
{
    use ItemProviderAwareTrait;
    use SecurityAwareTrait;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly RenditionManager $renditionManager,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        $item = $this->itemProvider->provide($operation, $uriVariables, $context);
        if (!$item instanceof Share) {
            return $item;
        }

        return $this->provideShare($item);
    }

    public function provideShare(Share $item): Share
    {
        $asset = $item->getAsset();

        $options = [
            AssetRenditionRepository::WITH_FILE => true,
        ];

        /** @var AssetRendition[] $renditions */
        $renditions = $this->em->getRepository(AssetRendition::class)->findAssetRenditions($asset->getId(), $options);

        foreach ($renditions as $rendition) {
            $definition = $rendition->getDefinition();
            if ($this->isGranted(AbstractVoter::READ, $rendition)) {
                $item->alternateUrls[] = new ShareAlternateUrlOutput(
                    $definition->getName(),
                    $this->urlGenerator->generate('share_public_rendition', [
                        'id' => $item->getId(),
                        'rendition' => $definition->getId(),
                        'token' => $item->getToken(),
                    ], UrlGeneratorInterface::ABS_URL),
                    $rendition->getFile()->getType(),
                );
            }
        }

        return $item;
    }
}
