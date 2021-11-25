<?php

declare(strict_types=1);

namespace App\Consumer\Handler\File;

use App\Entity\Core\Asset;
use App\Entity\Core\File;
use App\External\PhraseanetApiClient;
use App\Security\JWTTokenManager;
use App\Storage\UrlSigner;
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
    private UrlSigner $urlSigner;
    private UrlGeneratorInterface $urlGenerator;
    private JWTTokenManager $JWTTokenManager;

    public function __construct(
        PhraseanetApiClient $client,
        UrlSigner $urlSigner,
        UrlGeneratorInterface $urlGenerator,
        JWTTokenManager $JWTTokenManager,
        LoggerInterface $logger
    ) {
        $this->client = $client;
        $this->urlSigner = $urlSigner;
        $this->urlGenerator = $urlGenerator;
        $this->JWTTokenManager = $JWTTokenManager;
        $this->logger = $logger;
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

        $url = $this->urlSigner->getSignedUrl($file->getPath());
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

            throw $e;
        }
    }
    public static function getHandledEvents(): array
    {
        return [self::EVENT];
    }
}
