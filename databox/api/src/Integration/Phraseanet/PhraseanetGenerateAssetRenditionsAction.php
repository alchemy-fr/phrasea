<?php

declare(strict_types=1);

namespace App\Integration\Phraseanet;

use Alchemy\Workflow\Executor\JobContext;
use Alchemy\Workflow\Executor\RunContext;
use App\Asset\FileUrlResolver;
use App\External\PhraseanetApiClientFactory;
use App\Integration\AbstractIntegrationAction;
use App\Integration\IfActionInterface;
use App\Security\JWTTokenManager;
use GuzzleHttp\Exception\BadResponseException;
use Psr\Log\LoggerInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class PhraseanetGenerateAssetRenditionsAction extends AbstractIntegrationAction implements IfActionInterface
{
    public function __construct(
        private readonly PhraseanetApiClientFactory $clientFactory,
        private readonly FileUrlResolver $fileUrlResolver,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly JWTTokenManager $JWTTokenManager,
        private readonly LoggerInterface $logger
    ) {
    }

    public function handle(RunContext $context): void
    {
        $config = $this->getIntegrationConfig($context);
        $asset = $this->getAsset($context);
        $file = $asset->getSource();

        $integrationId = $config['integrationId'];

        $url = $this->fileUrlResolver->resolveUrl($file);

        $destUrl = $this->urlGenerator->generate('integration_phraseanet_incoming_rendition', [
            'integrationId' => $integrationId,
            'assetId' => $asset->getId(),
        ], UrlGeneratorInterface::ABSOLUTE_URL);

        $destination = [
            'url' => $destUrl,
            'payload' => [
                'token' => $this->JWTTokenManager->createToken($asset->getId()),
            ],
        ];

        $data = [
            'databoxId' => $config['databoxId'],
            'source' => [
                'url' => $url,
            ],
            'destination' => $destination,
        ];

        try {
            $this->clientFactory->create(
                $config['baseUrl'],
                $config['token'],
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

    public function shouldRun(JobContext $context): bool
    {
        $asset = $this->getAsset($context);
        if (null === $asset->getSource()) {
            return false;
        }

        return true;
    }
}
