<?php

declare(strict_types=1);

namespace App\Service\Asset;

use Alchemy\MessengerBundle\Listener\PostFlushStack;
use App\Consumer\Handler\Asset\BuildStoryContactSheet;
use App\Entity\Core\CollectionAsset;
use App\Repository\Core\CollectionAssetRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DeduplicateStamp;
use Symfony\Component\Messenger\Stamp\DelayStamp;

/**
 * Queues the rebuild of story contact sheets. A short delay and a deduplication
 * key per story merge the bursts (items added in a row, thumbnails built one by one).
 */
final readonly class StoryContactSheetScheduler
{
    public const int DELAY_MS = 3_000;
    public const int RETRY_DELAY_MS = 30_000;

    /**
     * Retries while items do not have their rendition yet. Beyond, the sheet is
     * built with the ready ones.
     */
    public const int MAX_ATTEMPTS = 10;

    public function __construct(
        private PostFlushStack $postFlushStack,
        private MessageBusInterface $bus,
        private EntityManagerInterface $em,
    ) {
    }

    /**
     * Dispatched once the current changes are flushed.
     */
    public function schedule(string $storyAssetId): void
    {
        $this->postFlushStack->addBusMessage($this->createEnvelope(new BuildStoryContactSheet($storyAssetId), self::DELAY_MS, 'story-sheet-'));
    }

    /**
     * For the stories the assets are items of, read now: call it before the
     * relations are removed.
     */
    public function scheduleForStoriesContaining(string ...$assetIds): void
    {
        /** @var CollectionAssetRepository $repository */
        $repository = $this->em->getRepository(CollectionAsset::class);

        foreach ($repository->findStoryAssetIdsContaining($assetIds) as $storyAssetId) {
            if (!in_array($storyAssetId, $assetIds, true)) {
                $this->schedule($storyAssetId);
            }
        }
    }

    /**
     * @param int $attempt from 1
     */
    public function scheduleRetry(string $storyAssetId, int $attempt): void
    {
        $this->bus->dispatch($this->createEnvelope(new BuildStoryContactSheet($storyAssetId, $attempt), self::RETRY_DELAY_MS, 'story-sheet-retry-'));
    }

    private function createEnvelope(BuildStoryContactSheet $message, int $delayMs, string $keyPrefix): Envelope
    {
        return new Envelope($message, [
            new DelayStamp($delayMs),
            // Released when the message is received: a change during the build queues a new one
            new DeduplicateStamp($keyPrefix.$message->getStoryAssetId(), ttl: ($delayMs + 60_000) / 1000, onlyDeduplicateInQueue: true),
        ]);
    }
}
