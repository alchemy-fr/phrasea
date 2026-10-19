<?php

declare(strict_types=1);

namespace App\Automation\Action;

use App\Attribute\AttributeTypeRegistry;
use App\Automation\AutomationActionState;
use App\Automation\AutomationBatch;
use App\Automation\AutomationRunContext;
use App\Entity\Core\Asset;
use App\Entity\Core\Attribute;
use App\Security\ClientUrlHelper;
use App\Service\Asset\Attribute\AssetNameResolver;
use App\Service\Asset\Attribute\AttributesResolver;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;
use Symfony\Component\HttpClient\NoPrivateNetworkHttpClient;
use Symfony\Component\HttpFoundation\IpUtils;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Calls a URL with the assets: once per batch (a failed call fails the assets of the batch)
 * or once at the end of the run with a summary of the processed assets.
 *
 * The body is signed with the optional secret (HMAC-SHA256, hex) in the X-Phrasea-Signature
 * header as "sha256=<signature>". Outside the dev environment, private and loopback hosts are
 * refused, at writing and when calling (after DNS resolution and on each redirect).
 */
#[AsTaggedItem(index: self::TYPE)]
final class WebhookAction extends AbstractAutomationAction
{
    public const string TYPE = 'webhook';

    final public const string MODE_PER_BATCH = 'per_batch';
    final public const string MODE_SUMMARY = 'summary';
    final public const array MODES = [self::MODE_PER_BATCH, self::MODE_SUMMARY];
    final public const array METHODS = ['POST', 'PUT'];

    final public const string EVENT_BATCH = 'automation.batch';
    final public const string EVENT_SUMMARY = 'automation.summary';

    final public const string SIGNATURE_HEADER = 'X-Phrasea-Signature';
    final public const string EVENT_HEADER = 'X-Phrasea-Event';

    /**
     * Maximum number of assets listed by a summary.
     */
    final public const int SUMMARY_MAX_ASSETS = 10_000;

    private const int MAX_HEADERS = 20;
    private const int MAX_URL_LENGTH = 2048;
    private const array RESERVED_HEADERS = ['host', 'content-type', 'content-length', 'x-phrasea-signature', 'x-phrasea-event'];

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly ClientUrlHelper $clientUrlHelper,
        private readonly AssetNameResolver $assetNameResolver,
        private readonly AttributesResolver $attributesResolver,
        private readonly AttributeTypeRegistry $attributeTypeRegistry,
        private readonly string $kernelEnv,
    ) {
    }

    public function validateConfig(array $config): array
    {
        $url = self::getRequiredString($config, 'url');
        if (strlen($url) > self::MAX_URL_LENGTH) {
            self::throwViolations(['url' => sprintf('The URL must not be longer than %d characters.', self::MAX_URL_LENGTH)]);
        }
        if (null !== $error = $this->getUrlError($url)) {
            self::throwViolations(['url' => $error]);
        }

        $method = strtoupper((string) ($config['method'] ?? 'POST'));
        if (!in_array($method, self::METHODS, true)) {
            self::throwViolations(['method' => sprintf('The method must be one of: %s.', implode(', ', self::METHODS))]);
        }

        $mode = $config['mode'] ?? self::MODE_PER_BATCH;
        if (!in_array($mode, self::MODES, true)) {
            self::throwViolations(['mode' => sprintf('The mode must be one of: %s.', implode(', ', self::MODES))]);
        }

        $includeAttributes = $config['includeAttributes'] ?? false;
        if (!is_bool($includeAttributes)) {
            self::throwViolations(['includeAttributes' => 'This value should be a boolean.']);
        }

        $secret = $config['secret'] ?? null;
        if (null !== $secret && !is_string($secret)) {
            self::throwViolations(['secret' => 'This value should be a string.']);
        }

        return [
            'url' => $url,
            'method' => $method,
            'headers' => self::normalizeHeaders($config['headers'] ?? []),
            'secret' => $secret,
            'mode' => $mode,
            'includeAttributes' => $includeAttributes,
        ];
    }

    public function getSecretFields(): array
    {
        return ['secret'];
    }

    public function processBatch(AutomationBatch $batch): void
    {
        $config = $batch->config;
        $state = $batch->state;
        $includeAttributes = $config['includeAttributes'] ?? false;

        if (self::MODE_SUMMARY === ($config['mode'] ?? self::MODE_PER_BATCH)) {
            $state->data['total'] ??= 0;
            $state->data['assets'] ??= [];
            foreach ($batch->assets as $asset) {
                if (count($state->data['assets']) < self::SUMMARY_MAX_ASSETS) {
                    $state->data['assets'][] = $this->describeAsset($asset, $includeAttributes);
                }
                ++$state->data['total'];
                $state->markProcessed($asset);
            }

            return;
        }

        if (empty($batch->assets)) {
            return;
        }

        $state->data['part'] = ($state->data['part'] ?? 0) + 1;
        $payload = $this->createPayload(self::EVENT_BATCH, $batch->context) + [
            'part' => $state->data['part'],
            'assets' => array_map(fn (Asset $asset): array => $this->describeAsset($asset, $includeAttributes), $batch->assets),
        ];

        try {
            $this->send($config, self::EVENT_BATCH, $payload);
        } catch (\Throwable $e) {
            foreach ($batch->assets as $asset) {
                $state->markFailed($asset, $e->getMessage());
            }

            return;
        }

        foreach ($batch->assets as $asset) {
            $state->markProcessed($asset);
        }
    }

    public function finish(AutomationActionState $state, array $config, AutomationRunContext $context): void
    {
        if (self::MODE_SUMMARY !== ($config['mode'] ?? self::MODE_PER_BATCH)) {
            return;
        }

        $total = $state->data['total'] ?? 0;
        if (0 === $total) {
            return;
        }

        $assets = $state->data['assets'] ?? [];
        $this->send($config, self::EVENT_SUMMARY, $this->createPayload(self::EVENT_SUMMARY, $context) + [
            'total' => $total,
            'truncated' => $total > count($assets),
            'assets' => $assets,
        ]);
    }

    /**
     * @throws \RuntimeException
     */
    private function send(array $config, string $event, array $payload): void
    {
        $body = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

        $headers = $config['headers'] ?? [];
        $headers['Content-Type'] = 'application/json';
        $headers[self::EVENT_HEADER] = $event;
        $secret = $config['secret'] ?? null;
        if (is_string($secret) && '' !== $secret) {
            $headers[self::SIGNATURE_HEADER] = self::sign($body, $secret);
        }

        $response = $this->getClient()->request($config['method'] ?? 'POST', $config['url'], [
            'headers' => $headers,
            'body' => $body,
            'timeout' => 10,
            'max_duration' => 30,
            'max_redirects' => 3,
        ]);

        $statusCode = $response->getStatusCode();
        if ($statusCode < 200 || $statusCode >= 300) {
            throw new \RuntimeException(sprintf('The webhook answered with HTTP status %d', $statusCode));
        }
    }

    public static function sign(string $body, string $secret): string
    {
        return 'sha256='.hash_hmac('sha256', $body, $secret);
    }

    private function getClient(): HttpClientInterface
    {
        return $this->allowsPrivateNetworks() ? $this->httpClient : new NoPrivateNetworkHttpClient($this->httpClient);
    }

    private function allowsPrivateNetworks(): bool
    {
        return 'dev' === $this->kernelEnv;
    }

    private function getUrlError(string $url): ?string
    {
        $parts = parse_url($url) ?: [];
        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = (string) ($parts['host'] ?? '');
        if (!in_array($scheme, ['http', 'https'], true) || '' === $host) {
            return 'The URL must be an absolute http(s) URL.';
        }

        if ($this->allowsPrivateNetworks()) {
            return null;
        }

        $host = strtolower(trim($host, '[]'));
        if ('localhost' === $host || str_ends_with($host, '.localhost')) {
            return 'Private and local hosts are not allowed.';
        }

        if (false !== filter_var($host, FILTER_VALIDATE_IP)) {
            $ips = [$host];
        } else {
            // Unresolved hosts are checked again when called
            $ips = gethostbynamel($host) ?: [];
        }
        foreach ($ips as $ip) {
            if (IpUtils::isPrivateIp($ip)
                || false === filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                return 'Private and local hosts are not allowed.';
            }
        }

        return null;
    }

    /**
     * @return array<string, string>
     */
    private static function normalizeHeaders(mixed $headers): array
    {
        if (!is_array($headers)) {
            self::throwViolations(['headers' => 'This value should be a map of header values.']);
        }
        if (count($headers) > self::MAX_HEADERS) {
            self::throwViolations(['headers' => sprintf('At most %d headers are allowed.', self::MAX_HEADERS)]);
        }

        $normalized = [];
        foreach ($headers as $name => $value) {
            $name = trim((string) $name);
            if (!preg_match('/^[A-Za-z0-9!#$%&\'*+.^_`|~-]+$/', $name)) {
                self::throwViolations(['headers' => sprintf('"%s" is not a valid header name.', $name)]);
            }
            if (in_array(strtolower($name), self::RESERVED_HEADERS, true)) {
                self::throwViolations(['headers' => sprintf('The header "%s" is set by Phrasea.', $name)]);
            }
            if (!is_scalar($value) || preg_match('/[\r\n\0]/', (string) $value)) {
                self::throwViolations(['headers' => sprintf('The value of the header "%s" is not valid.', $name)]);
            }
            $normalized[$name] = (string) $value;
        }

        return $normalized;
    }

    private function createPayload(string $event, AutomationRunContext $context): array
    {
        return [
            'event' => $event,
            'automation' => [
                'id' => $context->automationId,
                'name' => $context->automationName,
            ],
            'savedSearch' => [
                'id' => $context->savedSearchId,
                'name' => $context->savedSearchName,
            ],
            'run' => [
                'id' => $context->runId,
                'trigger' => $context->trigger->value,
            ],
        ];
    }

    private function describeAsset(Asset $asset, bool $includeAttributes): array
    {
        $index = $this->attributesResolver->resolveAssetAttributes($asset, true);

        $description = [
            'id' => $asset->getId(),
            'title' => $this->assetNameResolver->resolveNameAsString($asset, $index),
            'workspaceId' => $asset->getWorkspaceId(),
            'url' => $this->clientUrlHelper->generateAssetUrl($asset),
        ];

        if ($includeAttributes) {
            $attributes = [];
            foreach ($index->getDefinitions() as $definitionIndex) {
                $definition = $definitionIndex->getDefinition();
                $type = $this->attributeTypeRegistry->getStrictType((string) $definition->getType());
                $toString = fn (Attribute $attribute): string => $type->getStringValue($attribute->getValue(), $attribute->getLocale());

                $values = [];
                foreach ($definitionIndex->getLocales() as $locale => $value) {
                    $values[$locale] = is_array($value) ? array_map($toString, $value) : $toString($value);
                }
                $attributes[$definition->getSlug() ?? $definition->getName()] = $values;
            }
            $description['attributes'] = $attributes;
        }

        return $description;
    }
}
