<?php

declare(strict_types=1);

namespace App\Integration\Phraseanet;

use Alchemy\Workflow\Executor\JobContext;
use Alchemy\Workflow\Executor\JobExecutionContext;
use Alchemy\Workflow\Executor\RunContext;
use App\Entity\Core\Asset;
use App\External\PhraseanetApiClientFactory;
use App\Integration\AbstractIntegrationAction;
use App\Integration\IfActionInterface;
use GuzzleHttp\Exception\BadResponseException;
use Psr\Log\LoggerInterface;

final class PhraseanetGenerateAssetRenditionsEnqueueMethodAction extends AbstractIntegrationAction implements IfActionInterface
{
    public function __construct(
        private readonly PhraseanetApiClientFactory $clientFactory,
        private readonly LoggerInterface $logger,
        private readonly string $databoxBaseUrl,
    ) {
    }

    public function handle(RunContext $context): void
    {
        $config = $this->getIntegrationConfig($context);
        $asset = $this->getAsset($context);

        $data = [
            'assets' => [$asset->getId()],
            'publisher' => $asset->getOwnerId(),
            'commit_id' => $asset->getId(),
            'token' => self::generateAssetToken($asset), // TODO Add app secret
            'base_url' => $this->databoxBaseUrl.'/integrations/phraseanet/'.$config['integrationId'].'/',
            'formData' => [
                'collection_destination' => $config['collectionId'],
            ],
        ];

        $client = $this->clientFactory->create(
            $config['baseUrl'],
            $config['token']
        );

        try {
            $client->post('/api/v1/upload/enqueue/', [
                'json' => $data,
            ]);
        } catch (BadResponseException $e) {
            $this->logger->debug('Payload sent before error: '.\GuzzleHttp\json_encode($data));
            $this->logger->debug('Response: '.\GuzzleHttp\json_encode($e->getResponse()->getBody()->getContents()));

            throw $e;
        }
    }

    public static function generateAssetToken(Asset $asset): string
    {
        return sprintf('%s::%s', $asset->getId(), $asset->getCreatedAt()->getTimestamp());
    }

    protected function shouldRun(Asset $asset): bool
    {
        if (null === $asset->getSource()) {
            return false;
        }

        return true;
    }
}
