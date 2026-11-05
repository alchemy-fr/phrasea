<?php

declare(strict_types=1);

namespace App\Consumer\Handler\Asset;

use App\Entity\Core\Asset;
use App\Service\Asset\RenditionBuild\Exception\RenditionBuildException;
use App\Service\Asset\RenditionBuilder;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class BuildStoryContactSheetHandler
{
    public function __construct(
        private EntityManagerInterface $em,
        private RenditionBuilder $renditionBuilder,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(BuildStoryContactSheet $message): void
    {
        $story = $this->em->find(Asset::class, $message->getStoryAssetId());
        if (!$story instanceof Asset || !$story->isStory()) {
            return;
        }

        foreach ($this->renditionBuilder->getGenerateDefinitions($story->getWorkspaceId()) as $definition) {
            try {
                $this->renditionBuilder->buildRendition($definition, $story, attempt: $message->getAttempt());
            } catch (RenditionBuildException $e) {
                if (!$e->shouldSkipJob()) {
                    throw $e;
                }

                $this->logger->info(sprintf('Story contact sheet "%s" of asset "%s" skipped: %s', $definition->getName(), $story->getId(), $e->getMessage()));
            }
        }
    }
}
