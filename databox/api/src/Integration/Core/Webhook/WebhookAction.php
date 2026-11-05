<?php

declare(strict_types=1);

namespace App\Integration\Core\Webhook;

use Alchemy\Workflow\Executor\RunContext;
use App\Integration\AbstractIntegrationAction;
use App\Service\Asset\Attribute\AssetNameResolver;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpClient\NoPrivateNetworkHttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class WebhookAction extends AbstractIntegrationAction
{
    /**
     * The request options the integration editor may set: no proxy, DNS override or callback.
     */
    private const array ALLOWED_OPTIONS = ['headers', 'query', 'json', 'body', 'timeout', 'max_duration'];

    public function __construct(
        private readonly HttpClientInterface $client,
        private readonly string $databoxBaseUrl,
        private readonly AssetNameResolver $assetNameResolver,
        #[Autowire(param: 'kernel.environment')]
        private readonly string $kernelEnv,
    ) {
    }

    public function doHandle(RunContext $context): void
    {
        $asset = $this->getAsset($context);
        $config = $this->getIntegrationConfig($context);

        if (empty($config['url'])) {
            throw new \InvalidArgumentException('Missing or empty "url"');
        }

        $options = array_intersect_key($config['options'] ?? [], array_flip(self::ALLOWED_OPTIONS));

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

        // The editor of the integration chooses the URL, and reads the response in the workflow outputs
        $client = 'dev' === $this->kernelEnv ? $this->client : new NoPrivateNetworkHttpClient($this->client);
        $response = $client->request(
            $config['method'] ?? 'POST',
            $config['url'],
            $options,
        );

        $context->setOutput('status_code', $response->getStatusCode());
        $context->setOutput('body', $response->getContent(false));
    }
}
