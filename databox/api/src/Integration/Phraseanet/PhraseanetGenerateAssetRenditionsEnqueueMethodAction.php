<?php

declare(strict_types=1);

namespace App\Integration\Phraseanet;

use Alchemy\Workflow\Executor\RunContext;
use App\Entity\Core\Asset;
use App\Integration\AbstractIntegrationAction;
use App\Integration\IfActionInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpClient\Exception\ClientException;

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
            $client->request('POST', '/api/v1/upload/enqueue/', [
                'json' => $data,
            ]);
        } catch (ClientException $e) {
            $this->logger->debug('Payload sent before error: '.json_encode($data, JSON_THROW_ON_ERROR));
            $this->logger->debug('Response: '.json_encode($e->getResponse()->getContent(false), JSON_THROW_ON_ERROR));

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
