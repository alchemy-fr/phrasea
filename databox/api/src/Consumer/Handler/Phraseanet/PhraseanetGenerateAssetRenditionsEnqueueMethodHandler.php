<?php

declare(strict_types=1);

namespace App\Consumer\Handler\Phraseanet;

use App\Entity\Core\Asset;
use App\Entity\Core\File;
use App\External\PhraseanetApiClientFactory;
use App\Integration\IntegrationManager;
use Arthem\Bundle\RabbitBundle\Consumer\Event\AbstractEntityManagerHandler;
use Arthem\Bundle\RabbitBundle\Consumer\Event\EventMessage;
use Arthem\Bundle\RabbitBundle\Consumer\Exception\ObjectNotFoundForHandlerException;
use GuzzleHttp\Exception\BadResponseException;
use Psr\Log\LoggerInterface;

class PhraseanetGenerateAssetRenditionsEnqueueMethodHandler extends AbstractEntityManagerHandler
{
    const EVENT = 'phraseanet_generate_renditions_enqueue_method';

    private PhraseanetApiClientFactory $clientFactory;
    private string $databoxBaseUrl;
    private IntegrationManager $integrationManager;

    public function __construct(
        IntegrationManager $integrationManager,
        PhraseanetApiClientFactory $clientFactory,
        LoggerInterface $logger,
        string $databoxBaseUrl
    ) {
        $this->clientFactory = $clientFactory;
        $this->logger = $logger;
        $this->databoxBaseUrl = $databoxBaseUrl;
        $this->integrationManager = $integrationManager;
    }

    public static function createEvent(string $id, string $integrationId): EventMessage
    {
        $payload = [
            'id' => $id,
            'integrationId' => $integrationId,
        ];

        return new EventMessage(self::EVENT, $payload);
    }

    public function handle(EventMessage $message): void
    {
        $payload = $message->getPayload();
        $id = $payload['id'];

        $integration = $this->integrationManager->loadIntegration($payload['integrationId']);
        $options = $this->integrationManager->getIntegrationConfiguration($integration);

        $em = $this->getEntityManager();
        $asset = $em->find(Asset::class, $id);
        if (!$asset instanceof Asset) {
            throw new ObjectNotFoundForHandlerException(Asset::class, $id, __CLASS__);
        }

        if (!$asset->getFile() instanceof File) {
            $this->logger->warning(sprintf('%s error: Asset %s has no file', __CLASS__, $asset->getId()));

            return;
        }

        $data = [
            'assets' => [$asset->getId()],
            'publisher' => $asset->getOwnerId(),
            'commit_id' => $asset->getId(),
            'token' => self::generateAssetToken($asset), // TODO Add app secret
            'base_url' => $this->databoxBaseUrl.'/integrations/phraseanet/'.$integration->getId().'/',
            'formData' => [
                'collection_destination' => $options['collectionId'],
            ],
        ];

        $client = $this->clientFactory->create(
            $options['baseUrl'],
            $options['token']
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

    public static function getHandledEvents(): array
    {
        return [self::EVENT];
    }
}
