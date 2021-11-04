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
use InvalidArgumentException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class GenerateAssetSubDefinitionsHandler extends AbstractEntityManagerHandler
{
    const EVENT = 'generate_asset_sub_definitions';

    private PhraseanetApiClient $client;
    private UrlSigner $urlSigner;
    private UrlGeneratorInterface $urlGenerator;
    private JWTTokenManager $JWTTokenManager;

    public function __construct(
        PhraseanetApiClient $client,
        UrlSigner $urlSigner,
        UrlGeneratorInterface $urlGenerator,
        JWTTokenManager $JWTTokenManager
    ) {
        $this->client = $client;
        $this->urlSigner = $urlSigner;
        $this->urlGenerator = $urlGenerator;
        $this->JWTTokenManager = $JWTTokenManager;
    }

    public function handle(EventMessage $message): void
    {
        $payload = $message->getPayload();
        $id = $payload['id'];

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
        $destUrl = $this->urlGenerator->generate('phraseanet_incoming_subdef', [
            'assetId' => $asset->getId(),
        ], UrlGeneratorInterface::ABSOLUTE_URL);

        $workspace = $asset->getWorkspace();
        $phraseanetDataboxId = $workspace->getPhraseanetDataboxId();
        if (null === $phraseanetDataboxId) {
            throw new InvalidArgumentException(sprintf('phraseanetDataboxId is not set on workspace "%s"', $workspace->getId()));
        }

        $data = [
            'databoxId' => $phraseanetDataboxId,
            'source' => [
                'url' => $url,
            ],
            'destination' => [
                'url' => $destUrl,
                'payload' => [
                    'token' => $this->JWTTokenManager->createToken($asset->getId()),
                ],
            ]
        ];

        try {
            $this->client->post('/api/v3/subdefs_service/', [
                'json' => $data
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
