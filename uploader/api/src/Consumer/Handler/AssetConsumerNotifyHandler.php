<?php

declare(strict_types=1);

namespace App\Consumer\Handler;

use Alchemy\CoreBundle\Util\DoctrineUtil;
use App\Entity\Asset;
use App\Entity\Commit;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[AsMessageHandler]
final readonly class AssetConsumerNotifyHandler
{
    final public const string DEFAULT_AUTHORIZATION_SCHEME = 'ApiKey';

    public function __construct(
        private HttpClientInterface $client,
        private EntityManagerInterface $em,
        private string $uploaderUrl,
    ) {
    }

    public function __invoke(AssetConsumerNotify $message): void
    {
        $id = $message->getId();
        $commit = DoctrineUtil::findStrict($this->em, Commit::class, $id);
        $target = $commit->getTarget();
        $authorizationKey = $target->getAuthorizationKey();

        if (empty($target->getTargetUrl()) || 'avoid' === $authorizationKey) {
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
                'Authorization' => ($target->getAuthorizationScheme() ?? self::DEFAULT_AUTHORIZATION_SCHEME).' '.$authorizationKey,
            ],
            'json' => $arr,
        ])->getContent();
    }
}
