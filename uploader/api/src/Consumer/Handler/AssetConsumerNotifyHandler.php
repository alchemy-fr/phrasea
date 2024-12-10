<?php

declare(strict_types=1);

namespace App\Consumer\Handler;

use Alchemy\CoreBundle\Util\DoctrineUtil;
use Alchemy\NotifyBundle\Notification\NotifierInterface;
use App\Entity\Asset;
use App\Entity\Commit;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[AsMessageHandler]
final readonly class AssetConsumerNotifyHandler
{
    public function __construct(
        private HttpClientInterface $client,
        private EntityManagerInterface $em,
        private NotifierInterface $notifier,
        private string $uploaderUrl,
    ) {
    }

    public function __invoke(AssetConsumerNotify $message): void
    {
        $id = $message->getId();
        $commit = DoctrineUtil::findStrict($this->em, Commit::class, $id);
        $target = $commit->getTarget();
        $accessToken = $target->getTargetAccessToken();

        $this->notifier->notifyUser(
            $commit->getUserId(),
            'uploader-commit-acknowledged',
            [
                'assetCount' => $commit->getAssets()->count(),
            ]
        );

        if (empty($target->getTargetUrl()) || 'avoid' === $accessToken) {
            return;
        }

        $assets = array_map(fn (Asset $asset): string => $asset->getId(), $commit->getAssets()->toArray());
        if (empty($assets)) {
            throw new \RuntimeException('There is no asset');
        }

        $arr = [
            'assets' => $assets,
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
        ])->getContent();
    }
}
