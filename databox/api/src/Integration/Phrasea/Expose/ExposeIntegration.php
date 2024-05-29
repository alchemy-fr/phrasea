<?php

declare(strict_types=1);

namespace App\Integration\Phrasea\Expose;

use App\Entity\Basket\Basket;
use App\Entity\Integration\AbstractIntegrationData;
use App\Entity\Integration\IntegrationBasketData;
use App\Integration\AbstractActionIntegration;
use App\Integration\Auth\IntegrationTokenTrait;
use App\Integration\BasketActionsIntegrationInterface;
use App\Integration\IntegrationConfig;
use App\Integration\IntegrationDataTransformerInterface;
use Symfony\Component\Config\Definition\Builder\NodeBuilder;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Validator\Constraints\Url;

class ExposeIntegration extends AbstractActionIntegration implements BasketActionsIntegrationInterface, IntegrationDataTransformerInterface
{
    private const DATA_PUBLICATION_ID = 'publication_id';
    private const DATA_PUBLICATION = 'publication';

    use IntegrationTokenTrait;

    public function __construct(
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly ExposeClient $exposeClient,
    )
    {
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

    public function handleBasketAction(
        string $action,
        Request $request,
        Basket $basket,
        IntegrationConfig $config
    ): ?Response {
        switch ($action) {
            case 'sync':
                $integrationToken = $this->getIntegrationToken($config->getWorkspaceIntegration());
                if (null === $integrationToken) {
                    throw new \InvalidArgumentException('Missing integration token');
                }
                $data = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);
                $response = $this->exposeClient->createPublications($config, $integrationToken, $data);

                $publicationId = $response['id'];

                $integrationData = $this->integrationDataManager->storeBasketData(
                    $config->getWorkspaceIntegration(),
                    $basket,
                    self::DATA_PUBLICATION_ID,
                    $publicationId,
                    multiple: true,
                );

                return $this->createNewDataResponse($integrationData);
                break;
            case 'stop':
                $data = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);
                $id = $data['id'] ?? throw new \InvalidArgumentException('Missing "id"');;
                $deletePublication = !empty($data['deletePublication']);

                if ($deletePublication) {
                    $integrationToken = $this->getIntegrationToken($config->getWorkspaceIntegration());
                    if (null === $integrationToken) {
                        throw new \InvalidArgumentException('Missing integration token');
                    }

                    $intData = $this->integrationDataManager->getById(IntegrationBasketData::class, $config->getWorkspaceIntegration(), $id);
                    $publicationId = $intData->getValue();

                    $this->exposeClient->deletePublication($config, $integrationToken, $publicationId);
                }

                $this->integrationDataManager->deleteBasketDataById($config->getWorkspaceIntegration(), $id);
                break;
            default:
                throw new \InvalidArgumentException(sprintf('Unsupported basket action "%s"', $action));
        }

        return null;
    }

    /**
     * @param IntegrationBasketData $data
     */
    public function transformData(AbstractIntegrationData $data, IntegrationConfig $config): void
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
}
