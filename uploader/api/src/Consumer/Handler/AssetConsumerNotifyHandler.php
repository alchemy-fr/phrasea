<?php

declare(strict_types=1);

namespace App\Consumer\Handler;

use App\Entity\Asset;
use App\Entity\Commit;
use Arthem\Bundle\RabbitBundle\Consumer\Event\AbstractEntityManagerHandler;
use Arthem\Bundle\RabbitBundle\Consumer\Event\EventMessage;
use Arthem\Bundle\RabbitBundle\Consumer\Exception\ObjectNotFoundForHandlerException;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Notify remote consumer that there is a new batch available.
 */
class AssetConsumerNotifyHandler extends AbstractEntityManagerHandler
{
    final public const EVENT = 'asset_consumer_notify';

    public function __construct(
        private readonly HttpClientInterface $client,
        private readonly string $uploaderUrl
    ) {
    }

    public function handle(EventMessage $message): void
    {
        $id = $message->getPayload()['id'];
        $em = $this->getEntityManager();
        $commit = $em->find(Commit::class, $id);
        if (!$commit instanceof Commit) {
            throw new ObjectNotFoundForHandlerException(Commit::class, $id, self::class);
        }

        $target = $commit->getTarget();
        $accessToken = $target->getTargetAccessToken();
        if (empty($target->getTargetUrl()) || 'avoid' === $accessToken) {
            return;
        }

        $arr = [
            'assets' => array_map(fn (Asset $asset): string => $asset->getId(), $commit->getAssets()->toArray()),
            'publisher' => $commit->getUserId(),
            'commit_id' => $commit->getId(),
            'token' => $commit->getToken(),
            'base_url' => $this->uploaderUrl,
        ];
        $this->client->request('POST', $target->getTargetUrl(), [
            'headers' => [
                'Authorization' => ($target->getTargetTokenType() ?? 'Bearer').' '.$accessToken,
            ],
            'json' => $arr,
        ])->getStatusCode();
    }

    public static function getHandledEvents(): array
    {
        return [self::EVENT];
    }

    public static function getQueueName(): string
    {
        return 'asset_consumer_notify';
    }
}
