<?php

declare(strict_types=1);

namespace App\Consumer\Handler\Phraseanet;

use App\Asset\FileUrlResolver;
use App\Entity\Core\Asset;
use App\Entity\Core\File;
use App\External\PhraseanetApiClientFactory;
use App\Integration\IntegrationManager;
use App\Security\JWTTokenManager;
use Arthem\Bundle\RabbitBundle\Consumer\Event\AbstractEntityManagerHandler;
use Arthem\Bundle\RabbitBundle\Consumer\Event\EventMessage;
use Arthem\Bundle\RabbitBundle\Consumer\Exception\ObjectNotFoundForHandlerException;
use GuzzleHttp\Exception\BadResponseException;
use Psr\Log\LoggerInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class PhraseanetGenerateAssetRenditionsHandler extends AbstractEntityManagerHandler
{
    final public const EVENT = 'phraseanet_generate_renditions';

    public function __construct(
        private readonly IntegrationManager $integrationManager,
        private readonly PhraseanetApiClientFactory $clientFactory,
        private readonly FileUrlResolver $fileUrlResolver,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly JWTTokenManager $JWTTokenManager,
        LoggerInterface $logger
    ) {
        $this->logger = $logger;
    }

    public static function createEvent(string $id, string $integrationId, ?array $renditions = null): EventMessage
    {
        $payload = [
            'id' => $id,
            'integrationId' => $integrationId,
        ];

        if (null !== $renditions) {
            $payload['renditions'] = $renditions;
        }

        return new EventMessage(self::EVENT, $payload);
    }

    public function handle(EventMessage $message): void
    {
        $payload = $message->getPayload();
        $id = $payload['id'];

        $integration = $this->integrationManager->loadIntegration($payload['integrationId']);
        $options = $this->integrationManager->getIntegrationConfiguration($integration);

        $renditions = $payload['renditions'] ?? null;

        $em = $this->getEntityManager();
        $asset = $em->find(Asset::class, $id);
        if (!$asset instanceof Asset) {
            throw new ObjectNotFoundForHandlerException(Asset::class, $id, self::class);
        }

        $file = $asset->getSource();

        if (!$file instanceof File) {
            $this->logger->warning(sprintf('%s error: Asset %s has no file', self::class, $asset->getId()));

            return;
        }

        $url = $this->fileUrlResolver->resolveUrl($file);

        $destUrl = $this->urlGenerator->generate('integration_phraseanet_incoming_rendition', [
            'integrationId' => $integration->getId(),
            'assetId' => $asset->getId(),
        ], UrlGeneratorInterface::ABSOLUTE_URL);

        $destination = [
            'url' => $destUrl,
            'payload' => [
                'token' => $this->JWTTokenManager->createToken($asset->getId()),
            ],
        ];

        if (!empty($renditions)) {
            $destination['subdefs'] = $renditions;
        }

        $data = [
            'databoxId' => $options['databoxId'],
            'source' => [
                'url' => $url,
            ],
            'destination' => $destination,
        ];

        try {
            $this->clientFactory->create(
                $options['baseUrl'],
                $options['token'],
            )->post('/api/v3/subdefs_service/', [
                'json' => $data,
                'stream' => true,
                'read_timeout' => 10,
            ]);
        } catch (BadResponseException $e) {
            $this->logger->debug('Payload sent before error: '.\GuzzleHttp\json_encode($data));
            $this->logger->debug('Response: '.\GuzzleHttp\json_encode($e->getResponse()->getBody()->getContents()));

            throw $e;
        }
    }

    public static function getHandledEvents(): array
    {
        return [self::EVENT];
    }
}
