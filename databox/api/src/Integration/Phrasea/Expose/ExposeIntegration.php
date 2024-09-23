<?php

declare(strict_types=1);

namespace App\Integration\Phrasea\Expose;

use Alchemy\CoreBundle\Util\DoctrineUtil;
use App\Entity\Basket\Basket;
use App\Entity\Integration\IntegrationData;
use App\Integration\AbstractIntegration;
use App\Integration\Action\UserActionsTrait;
use App\Integration\Auth\IntegrationTokenTrait;
use App\Integration\BasketUpdateHandlerIntegrationInterface;
use App\Integration\IntegrationConfig;
use App\Integration\IntegrationContext;
use App\Integration\IntegrationDataTransformerInterface;
use App\Integration\Phrasea\Expose\Message\SyncBasket;
use App\Integration\UserActionsIntegrationInterface;
use Symfony\Component\Config\Definition\Builder\NodeBuilder;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Validator\Constraints\Url;

class ExposeIntegration extends AbstractIntegration implements UserActionsIntegrationInterface, IntegrationDataTransformerInterface, BasketUpdateHandlerIntegrationInterface
{
    use IntegrationTokenTrait;
    use UserActionsTrait;
    private const DATA_PUBLICATION_ID = 'publication_id';
    private const DATA_PUBLICATION = 'publication';

    public function __construct(
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly ExposeClient $exposeClient,
        private readonly MessageBusInterface $bus,
    ) {
    }

    public function buildConfiguration(NodeBuilder $builder): void
    {
        $builder
            ->scalarNode('baseUrl')
                ->defaultValue('${EXPOSE_API_URL}')
                ->cannotBeEmpty()
                ->info('The Expose API base URL')
            ->end()
            ->scalarNode('clientId')
                ->isRequired()
                ->cannotBeEmpty()
            ->end()
            ->scalarNode('clientUrl')
            ->defaultValue('${EXPOSE_CLIENT_URL}')
            ->cannotBeEmpty()
            ->info('The Expose Client base URL')
            ->end()
        ;
    }

    public function handleUserAction(
        string $action,
        Request $request,
        IntegrationConfig $config,
    ): ?Response {
        switch ($action) {
            case 'sync':
                $integrationToken = $this->getIntegrationToken($config->getWorkspaceIntegration());
                if (null === $integrationToken) {
                    throw new \InvalidArgumentException('Missing integration token');
                }

                $data = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);
                $basket = DoctrineUtil::findStrict($this->em, Basket::class, $data['basketId']);
                $response = $this->exposeClient->createPublications($config, $integrationToken, $data);

                $publicationId = $response['id'];

                $integrationData = $this->integrationDataManager->storeData(
                    $config->getWorkspaceIntegration(),
                    $this->getStrictUser()->getId(),
                    $basket,
                    self::DATA_PUBLICATION_ID,
                    $publicationId,
                    multiple: true,
                );

                $this->bus->dispatch(new SyncBasket($integrationData->getId()));

                return $this->createNewDataResponse($integrationData);
            case 'force-sync':
                $data = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);
                $intData = $this->integrationDataManager->getById(
                    $config->getWorkspaceIntegration(),
                    $data['id'],
                    $this->getStrictUser()->getId(),
                );

                $this->bus->dispatch(new SyncBasket($intData->getId()));

                return null;
            case 'stop':
                $data = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);
                $id = $data['id'] ?? throw new \InvalidArgumentException('Missing "id"');
                $deletePublication = !empty($data['deletePublication']);

                if ($deletePublication) {
                    $integrationToken = $this->getIntegrationToken($config->getWorkspaceIntegration());
                    if (null === $integrationToken) {
                        throw new \InvalidArgumentException('Missing integration token');
                    }

                    $intData = $this->integrationDataManager->getById(
                        $config->getWorkspaceIntegration(),
                        $id,
                        $this->getStrictUser()->getId(),
                    );
                    $publicationId = $intData->getValue();

                    $this->exposeClient->deletePublication($config, $integrationToken, $publicationId);
                }

                $this->integrationDataManager->deleteById(
                    $config->getWorkspaceIntegration(),
                    $id,
                    $this->getStrictUser()->getId(),
                );
                break;
            default:
                throw new \InvalidArgumentException(sprintf('Unsupported basket action "%s"', $action));
        }

        return null;
    }

    public function handleBasketUpdate(IntegrationData $data, IntegrationConfig $config): void
    {
        $this->bus->dispatch(new SyncBasket($data->getId()));
    }

    public function transformData(IntegrationData $data, IntegrationConfig $config): void
    {
        $publicationId = $data->getValue();

        $data->setValue([
            'id' => $publicationId,
            'url' => $config['clientUrl'].'/'.$publicationId,
        ]);
        $data->setName(self::DATA_PUBLICATION);
    }

    public function supportData(string $integrationName, string $dataName, IntegrationConfig $config): bool
    {
        return $integrationName === static::getName() && self::DATA_PUBLICATION_ID === $dataName;
    }

    public static function requiresWorkspace(): bool
    {
        return false;
    }

    public function validateConfiguration(IntegrationConfig $config): void
    {
        $this->validate($config, 'baseUrl', [
            new Url(),
        ]);
    }

    public function getConfigurationInfo(IntegrationConfig $config): array
    {
        return [
            'Redirect URI' => $this->urlGenerator->generate('integration_auth_code', [
                'integrationId' => $config->getIntegrationId(),
            ], UrlGeneratorInterface::ABSOLUTE_URL),
        ];
    }

    public static function getTitle(): string
    {
        return 'Expose';
    }

    public static function getName(): string
    {
        return 'phrasea.expose';
    }

    public function getSupportedContexts(): array
    {
        return [IntegrationContext::Basket];
    }
}
