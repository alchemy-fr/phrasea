<?php

declare(strict_types=1);

namespace App\Integration\Phraseanet;

use Alchemy\Workflow\Executor\RunContext;
use App\Asset\FileUrlResolver;
use App\Entity\Core\Asset;
use App\Integration\AbstractIntegrationAction;
use App\Integration\IfActionInterface;
use App\Security\JWTTokenManager;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpClient\Exception\ClientException;
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
            )->request('POST', '/api/v3/subdefs_service/', [
                'json' => $data,
            ]);
        } catch (ClientException $e) {
            $this->logger->debug('Payload sent before error: '.json_encode($data, JSON_THROW_ON_ERROR));
            $this->logger->debug('Response: '.json_encode($e->getResponse()->getContent(false), JSON_THROW_ON_ERROR));

            throw $e;
        }
    }

    protected function shouldRun(Asset $asset): bool
    {
        if (null === $asset->getSource()) {
            return false;
        }

        return true;
    }
}
