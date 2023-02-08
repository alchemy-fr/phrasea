<?php

declare(strict_types=1);

namespace App\Consumer\Handler;

use App\Entity\Asset;
use App\Entity\Commit;
use Arthem\Bundle\RabbitBundle\Consumer\Event\AbstractEntityManagerHandler;
use Arthem\Bundle\RabbitBundle\Consumer\Event\EventMessage;
use Arthem\Bundle\RabbitBundle\Consumer\Exception\ObjectNotFoundForHandlerException;
use GuzzleHttp\Client;

/**
 * Notify remote consumer that there is a new batch available.
 */
class AssetConsumerNotifyHandler extends AbstractEntityManagerHandler
{
    const EVENT = 'asset_consumer_notify';

    private Client $client;
    private string $uploadBaseUrl;

    public function __construct(
        Client $client,
        string $uploadBaseUrl
    ) {
        $this->client = $client;
        $this->uploadBaseUrl = $uploadBaseUrl;
    }

    public function handle(EventMessage $message): void
    {
        $id = $message->getPayload()['id'];
        $em = $this->getEntityManager();
        $commit = $em->find(Commit::class, $id);
        if (!$commit instanceof Commit) {
            throw new ObjectNotFoundForHandlerException(Commit::class, $id, __CLASS__);
        }

        $target = $commit->getTarget();
        $accessToken = $target->getTargetAccessToken();
        if ('avoid' === $accessToken) {
            return;
        }

        $arr = [
            'assets' => array_map(function (Asset $asset): string {
                return $asset->getId();
            }, $commit->getAssets()->toArray()),
            'publisher' => $commit->getUserId(),
            'commit_id' => $commit->getId(),
            'token' => $commit->getToken(),
            'base_url' => $this->uploadBaseUrl,
        ];
        $this->client->post($target->getTargetUrl(), [
            'headers' => [
                'Authorization' => ($target->getTargetTokenType() ?? 'Bearer').' '.$accessToken,
            ],
            'json' => $arr,
        ]);
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
