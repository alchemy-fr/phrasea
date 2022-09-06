<?php

declare(strict_types=1);

namespace App\Integration\Phraseanet;

use App\Consumer\Handler\Phraseanet\PhraseanetGenerateAssetRenditionsEnqueueMethodHandler;
use App\Consumer\Handler\Phraseanet\PhraseanetGenerateAssetRenditionsHandler;
use App\Entity\Core\Asset;
use App\Integration\AbstractIntegration;
use App\Integration\AssetOperationIntegrationInterface;
use Arthem\Bundle\RabbitBundle\Producer\EventProducer;
use Symfony\Component\OptionsResolver\Exception\InvalidOptionsException;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Validator\Constraints\Url;
use Symfony\Component\Validator\Validation;

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

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setRequired([
            'method',
            'baseUrl',
            'token',
        ]);
        $resolver->setDefaults([
            'databoxId' => null,
            'collectionId' => null,
        ]);
        $resolver->setAllowedTypes('baseUrl', 'string');
        $resolver->setAllowedTypes('token', 'string');
        $resolver->setAllowedTypes('databoxId', ['null', 'integer']);
        $resolver->setAllowedTypes('collectionId', ['null', 'integer']);
        $resolver->setAllowedValues('method', [
            self::METHOD_API,
            self::METHOD_ENQUEUE,
        ]);

        $resolver->setNormalizer('method', function(Options $options, $method) {
            if ($method === self::METHOD_API &&  empty($options['databoxId'])) {
                throw new InvalidOptionsException(sprintf('"databoxId" must be defined when using the "%s" method.', self::METHOD_API));
            } elseif ($method === self::METHOD_ENQUEUE &&  empty($options['collectionId'])) {
                throw new InvalidOptionsException(sprintf('"collectionId" must be defined when using the "%s" method.', self::METHOD_ENQUEUE));
            }

            return $method;
        });

        $resolver->setAllowedValues('baseUrl', Validation::createIsValidCallable(
            new Url()
        ));
    }

    public function getConfigurationInfo(array $options): array
    {
        $info = [];

        if ($options['method'] === self::METHOD_ENQUEUE) {
            $info['Webhook URL'] = $this->urlGenerator->generate('integration_phraseanet_webhook_event', [
                'integrationId' => $options['integrationId']
            ], UrlGeneratorInterface::ABSOLUTE_URL);
        }

        return $info;
    }

    public function handleAsset(Asset $asset, array $options): void
    {
        $integrationId = $options['integrationId'];
        if (self::METHOD_API === $options['method']) {
            $this->eventProducer->publish(PhraseanetGenerateAssetRenditionsHandler::createEvent($asset->getId(), $integrationId));
        } elseif (self::METHOD_ENQUEUE === $options['method']) {
            $this->eventProducer->publish(PhraseanetGenerateAssetRenditionsEnqueueMethodHandler::createEvent($asset->getId(), $integrationId));
        }
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
