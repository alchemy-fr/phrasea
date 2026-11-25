<?php

declare(strict_types=1);

namespace App\Consumer\Handler;

use Alchemy\CoreBundle\Util\DoctrineUtil;
use App\Consumer\TargetPrivateOrigins;
use App\Entity\Asset;
use App\Entity\AssetRepository;
use App\Entity\Commit;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpClient\NoPrivateNetworkHttpClient;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[AsMessageHandler]
final readonly class AssetConsumerNotifyHandler
{
    final public const string DEFAULT_AUTHORIZATION_SCHEME = 'ApiKey';
    private const int MAX_DURATION = 60;

    public function __construct(
        private HttpClientInterface $client,
        private EntityManagerInterface $em,
        private string $uploaderUrl,
        private TargetPrivateOrigins $privateOrigins,
    ) {
    }

    public function __invoke(AssetConsumerNotify $message): void
    {
        $id = $message->getId();
        $commit = DoctrineUtil::findStrict($this->em, Commit::class, $id);
        $target = $commit->getTarget();
        $authorizationKey = $target->getAuthorizationKey();
        $url = $target->getTargetUrl();

        if (empty($url) || 'avoid' === $authorizationKey) {
            return;
        }

        /** @var AssetRepository $assetRepository */
        $assetRepository = $this->em->getRepository(Asset::class);
        // Read from the database: a commit stored in this process lists none of its files
        $assets = $assetRepository->getCommitAssetIds($commit->getId());
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

        $options = [
            'headers' => [
                'Authorization' => ($target->getAuthorizationScheme() ?? self::DEFAULT_AUTHORIZATION_SCHEME).' '.$authorizationKey,
            ],
            'json' => $arr,
            'max_duration' => self::MAX_DURATION,
        ];

        if ($this->privateOrigins->isAllowed($url)) {
            $client = $this->client;
            // A redirection would leave the trusted origin unchecked
            $options['max_redirects'] = 0;
        } else {
            // Checks the redirections too
            $client = new NoPrivateNetworkHttpClient($this->client);
        }

        $client->request('POST', $url, $options)->getContent();
    }
}
