<?php

declare(strict_types=1);

namespace App\Integration\Phraseanet;

use App\Consumer\Handler\Phraseanet\PhraseanetGenerateAssetRenditionsEnqueueMethodHandler;
use App\Consumer\Handler\Phraseanet\PhraseanetGenerateAssetRenditionsHandler;
use App\Entity\Core\Asset;
use App\Integration\AbstractIntegration;
use App\Integration\AssetOperationIntegrationInterface;
use Arthem\Bundle\RabbitBundle\Producer\EventProducer;
use Symfony\Component\Config\Definition\Builder\NodeBuilder;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Validator\Constraints\Url;

class PhraseanetRenditionIntegration extends AbstractIntegration implements AssetOperationIntegrationInterface
{
    public const METHOD_ENQUEUE = 'enqueue';
    public const METHOD_API = 'api';

    private EventProducer $eventProducer;
    private UrlGeneratorInterface $urlGenerator;

    public function __construct(EventProducer $eventProducer, UrlGeneratorInterface $urlGenerator)
    {
        $this->eventProducer = $eventProducer;
        $this->urlGenerator = $urlGenerator;
    }

    public function buildConfiguration(NodeBuilder $builder): void
    {
        $allowedMethods = [
            self::METHOD_API,
            self::METHOD_ENQUEUE,
        ];

        $builder
            ->scalarNode('baseUrl')
                ->isRequired()
                ->cannotBeEmpty()
                ->info('The Phraseanet base URL')
            ->end()
            ->enumNode('method')
                ->isRequired()
                ->values($allowedMethods)
            ->end()
            ->integerNode('collectionId')
                ->info(sprintf('Required for the "%s" method', self::METHOD_ENQUEUE))
            ->end()
            ->integerNode('databoxId')
                ->info(sprintf('Required for the "%s" method', self::METHOD_API))
            ->end()
            ->scalarNode('token')
                ->isRequired()
                ->cannotBeEmpty()
                ->info('The Phraseanet API key')
            ->end()
        ;
    }

    public function validateConfiguration(array $config): void
    {
        $method = $config['method'];
        if (self::METHOD_API === $method && empty($config['databoxId'])) {
            throw new InvalidConfigurationException(sprintf('"databoxId" must be defined when using the "%s" method.', self::METHOD_API));
        }
        if (self::METHOD_ENQUEUE === $method && empty($config['collectionId'])) {
            throw new InvalidConfigurationException(sprintf('"collectionId" must be defined when using the "%s" method.', self::METHOD_ENQUEUE));
        }

        $this->validate($config, 'baseUrl', [
            new Url(),
        ]);
    }

    public function getConfigurationInfo(array $config): array
    {
        $info = [];

        if (self::METHOD_ENQUEUE === $config['method']) {
            $info['Webhook URL'] = $this->urlGenerator->generate('integration_phraseanet_webhook_event', [
                'integrationId' => $config['integrationId'],
            ], UrlGeneratorInterface::ABSOLUTE_URL);
        }

        return $info;
    }

    public function handleAsset(Asset $asset, array $config): void
    {
        $integrationId = $config['integrationId'];
        if (self::METHOD_API === $config['method']) {
            $this->eventProducer->publish(PhraseanetGenerateAssetRenditionsHandler::createEvent($asset->getId(), $integrationId));
        } elseif (self::METHOD_ENQUEUE === $config['method']) {
            $this->eventProducer->publish(PhraseanetGenerateAssetRenditionsEnqueueMethodHandler::createEvent($asset->getId(), $integrationId));
        }
    }

    public function supportsAsset(Asset $asset, array $config): bool
    {
        return null !== $asset->getSource();
    }

    public static function getTitle(): string
    {
        return 'Phraseanet renditions';
    }

    public static function getName(): string
    {
        return 'phraseanet.renditions';
    }
}
