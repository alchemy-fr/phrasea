<?php

declare(strict_types=1);

namespace App\Consumer\Handler\File;

use App\Asset\FileUrlResolver;
use App\Entity\Core\Asset;
use App\Entity\Core\File;
use App\External\PhraseanetApiClient;
use App\Security\JWTTokenManager;
use Arthem\Bundle\RabbitBundle\Consumer\Event\AbstractEntityManagerHandler;
use Arthem\Bundle\RabbitBundle\Consumer\Event\EventMessage;
use Arthem\Bundle\RabbitBundle\Consumer\Exception\ObjectNotFoundForHandlerException;
use GuzzleHttp\Exception\BadResponseException;
use Psr\Log\LoggerInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class GenerateAssetRenditionsHandler extends AbstractEntityManagerHandler
{
    const EVENT = 'generate_renditions';

    private PhraseanetApiClient $client;
    private UrlGeneratorInterface $urlGenerator;
    private JWTTokenManager $JWTTokenManager;
    private FileUrlResolver $fileUrlResolver;

    public function __construct(
        PhraseanetApiClient $client,
        FileUrlResolver $fileUrlResolver,
        UrlGeneratorInterface $urlGenerator,
        JWTTokenManager $JWTTokenManager,
        LoggerInterface $logger
    ) {
        $this->client = $client;
        $this->urlGenerator = $urlGenerator;
        $this->JWTTokenManager = $JWTTokenManager;
        $this->logger = $logger;
        $this->fileUrlResolver = $fileUrlResolver;
    }

    public static function createEvent(string $id, ?array $renditions = null): EventMessage
    {
        $payload = [
            'id' => $id,
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
        $renditions = $payload['renditions'] ?? null;

        $em = $this->getEntityManager();
        $asset = $em->find(Asset::class, $id);
        if (!$asset instanceof Asset) {
            throw new ObjectNotFoundForHandlerException(Asset::class, $id, __CLASS__);
        }

        $file = $asset->getFile();

        if (!$file instanceof File) {
            $this->logger->warning(sprintf('%s error: Asset %s has no file', __CLASS__, $asset->getId()));

            return;
        }

        $url = $this->fileUrlResolver->resolveUrl($file);

        $destUrl = $this->urlGenerator->generate('phraseanet_incoming_rendition', [
            'assetId' => $asset->getId(),
        ], UrlGeneratorInterface::ABSOLUTE_URL);

        $workspace = $asset->getWorkspace();
        $phraseanetDataboxId = $workspace->getPhraseanetDataboxId();
        if (null === $phraseanetDataboxId) {
            $this->logger->critical(sprintf('phraseanetDataboxId is not set on workspace "%s"', $workspace->getId()));

            return;
        }

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
            'databoxId' => $phraseanetDataboxId,
            'source' => [
                'url' => $url,
            ],
            'destination' => $destination,
        ];

        try {
            $this->client->post('/api/v3/subdefs_service/', [
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
