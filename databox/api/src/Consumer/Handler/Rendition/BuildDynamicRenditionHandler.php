<?php

declare(strict_types=1);

namespace App\Consumer\Handler\Rendition;

use Alchemy\CoreBundle\Util\DoctrineUtil;
use App\Entity\Core\AssetRendition;
use App\Service\Asset\RenditionBuild\Exception\RenditionBuildException;
use App\Service\Asset\RenditionBuilder;
use App\Service\Storage\RenditionManager;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class BuildDynamicRenditionHandler
{
    public function __construct(
        private RenditionBuilder $renditionBuilder,
        private RenditionManager $renditionManager,
        private EntityManagerInterface $em,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(BuildDynamicRendition $message): void
    {
        $rendition = DoctrineUtil::findStrict($this->em, AssetRendition::class, $message->getRenditionId());

        try {
            $this->renditionBuilder->buildDynamicRendition($rendition);
        } catch (\Throwable $e) {
            $this->logger->error('Dynamic rendition build failed', [
                'exception' => $e,
                'renditionId' => $rendition->getId(),
                'assetId' => $rendition->getAsset()->getId(),
            ]);

            // Remove the pending rendition and notify clients so the pending card disappears
            $this->renditionManager->pushRenditionUpdate($rendition);
            $this->em->remove($rendition);
            $this->em->flush();
        }
    }
}
