<?php

declare(strict_types=1);

namespace App\Consumer\Handler;

use App\Entity\Commit;
use Arthem\Bundle\RabbitBundle\Consumer\Event\AbstractEntityManagerHandler;
use Arthem\Bundle\RabbitBundle\Consumer\Event\EventMessage;
use Arthem\Bundle\RabbitBundle\Producer\EventProducer;

/**
 * Delete acknowledged asset after graceful period.
 */
class DeleteExpiredAssetsHandler extends AbstractEntityManagerHandler
{
    final public const EVENT = 'delete_expired_assets';

    public function __construct(private readonly MessageBusInterface $bus, private readonly int $deleteAssetGracefulTime)
    {
    }

    public function handle(EventMessage $message): void
    {
        if ($this->deleteAssetGracefulTime <= 0) {
            return;
        }

        $date = (new \DateTimeImmutable())
            ->setTimestamp(time() - $this->deleteAssetGracefulTime);

        $em = $this->getEntityManager();
        $commits = $em
            ->getRepository(Commit::class)
            ->getAcknowledgedBefore($date);

        foreach ($commits as $commit) {
            foreach ($commit->getAssets() as $asset) {
                $this->eventProducer->publish(new EventMessage(DeleteAssetFileHandler::EVENT, [
                    'path' => $asset->getPath(),
                ]));
                $em->remove($asset);
            }

            $em->remove($commit);
            $em->flush();
        }
    }

    public static function getHandledEvents(): array
    {
        return [self::EVENT];
    }

    public static function getQueueName(): string
    {
        return 'fast_events';
    }
}
