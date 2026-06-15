<?php

declare(strict_types=1);

namespace App\Integration\Core\Webhook;

use Alchemy\Workflow\Executor\RunContext;
use App\Integration\AbstractIntegrationAction;
use App\Service\Asset\Attribute\AssetNameResolver;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class WebhookAction extends AbstractIntegrationAction
{
    public function __construct(
        private readonly HttpClientInterface $client,
        private readonly string $databoxBaseUrl,
        private readonly AssetNameResolver $assetNameResolver,
    ) {
    }

    public function doHandle(RunContext $context): void
    {
        $asset = $this->getAsset($context);
        $config = $this->getIntegrationConfig($context);

        if (empty($config['url'])) {
            throw new \InvalidArgumentException('Missing or empty "url"');
        }

        $options = $config['options'] ?? [];

        if ($config['includeInputs']) {
            $options['json']['asset'] = [
                'id' => $asset->getId(),
                'name' => $this->assetNameResolver->resolveNameAsString($asset),
            ];
            $workspace = $asset->getWorkspace();
            $options['json']['workspace'] = [
                'id' => $workspace->getId(),
                'name' => $workspace->getName(),
            ];
        }

        if ($config['includeOrigin']) {
            $options['json']['origin'] = $this->databoxBaseUrl;
        }

        $response = $this->client->request(
            $config['method'] ?? 'POST',
            $config['url'],
            $options,
        );

        $context->setOutput('status_code', $response->getStatusCode());
        $context->setOutput('body', $response->getContent(false));
    }
}
