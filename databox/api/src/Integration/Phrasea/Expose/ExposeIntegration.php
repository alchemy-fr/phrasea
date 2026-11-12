<?php

declare(strict_types=1);

namespace App\Integration\Phrasea\Expose;

use Alchemy\CoreBundle\Util\DoctrineUtil;
use App\Entity\Basket\Basket;
use App\Entity\Integration\IntegrationData;
use App\Integration\AbstractIntegration;
use App\Integration\Action\UserActionsTrait;
use App\Integration\Auth\IntegrationTokenRenewerInterface;
use App\Integration\Auth\IntegrationTokenTrait;
use App\Integration\BasketUpdateHandlerIntegrationInterface;
use App\Integration\Config\ConfigWidget;
use App\Integration\IntegrationCategory;
use App\Integration\IntegrationConfig;
use App\Integration\IntegrationContext;
use App\Integration\IntegrationDataTransformerInterface;
use App\Integration\Phrasea\Expose\Message\SyncBasket;
use App\Integration\Phrasea\PhraseaClientFactory;
use App\Integration\UserActionsIntegrationInterface;
use App\Security\Voter\AbstractVoter;
use Symfony\Component\Config\Definition\Builder\NodeBuilder;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Validator\Constraints\Url;

#[AsTaggedItem(index: self::NAME)]
class ExposeIntegration extends AbstractIntegration implements UserActionsIntegrationInterface, IntegrationDataTransformerInterface, BasketUpdateHandlerIntegrationInterface, IntegrationTokenRenewerInterface
{
    use IntegrationTokenTrait;
    use UserActionsTrait;

    public const string NAME = 'phrasea.expose';
    private const string DATA_PUBLICATION_ID = 'publication_id';
    private const string DATA_PUBLICATION = 'publication';

    public function __construct(
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly ExposeClient $exposeClient,
        private readonly MessageBusInterface $bus,
        private readonly PhraseaClientFactory $clientFactory,
    ) {
    }

    public function renewIntegrationToken(IntegrationConfig $config, string $refreshToken): array
    {
        return $this->clientFactory->createTokenRenewer($config['baseUrl'], $config['clientId'])($refreshToken);
    }

    public function buildConfiguration(NodeBuilder $builder): void
    {
        $builder
            ->scalarNode('baseUrl')
                ->defaultValue('${EXPOSE_API_URL}')
                ->cannotBeEmpty()
                ->info('The Expose API base URL')
            ->end();
        ConfigWidget::apply($builder->scalarNode('clientId'), ConfigWidget::OAuthClient)
                ->isRequired()
                ->cannotBeEmpty()
                ->info('Keycloak OAuth client (public, standard flow) used to authorize users on Expose')
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
        $userId = $this->getStrictUserOrOAuthClient()->getUserIdentifier();
        switch ($action) {
            case 'sync':
                $integrationToken = $this->getIntegrationToken($config->getWorkspaceIntegration());
                if (null === $integrationToken) {
                    throw new \InvalidArgumentException('Missing integration token');
                }

                $data = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);
                $basket = DoctrineUtil::findStrict($this->em, Basket::class, $data['basketId']);
                // The basket assets are published: it must be one the user can read
                $this->denyAccessUnlessGranted(AbstractVoter::READ, $basket);

                // Fixed for the life of the publication. Not an Expose property: the body is sent as is.
                $ignoreRoi = filter_var($data['ignoreRoi'] ?? false, FILTER_VALIDATE_BOOL);
                unset($data['ignoreRoi']);

                $response = $this->exposeClient->createPublication($config, $integrationToken, $data);

                $publicationId = $response['id'];

                $integrationData = $this->integrationDataManager->storeData(
                    $config->getWorkspaceIntegration(),
                    $userId,
                    $basket,
                    self::DATA_PUBLICATION_ID,
                    json_encode([
                        'id' => $publicationId,
                        'ignoreRoi' => $ignoreRoi,
                    ], JSON_THROW_ON_ERROR),
                    multiple: true,
                );

                $this->bus->dispatch(new SyncBasket($integrationData->getId()));

                return $this->createNewDataResponse($integrationData);
            case 'force-sync':
                $data = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);
                $intData = $this->integrationDataManager->getById(
                    $config->getWorkspaceIntegration(),
                    $data['id'],
                    $userId,
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
                        $userId,
                    );
                    $publicationId = self::readPublicationData($intData)['id'];

                    $this->exposeClient->deletePublication($config, $integrationToken, $publicationId);
                }

                $this->integrationDataManager->deleteById(
                    $config->getWorkspaceIntegration(),
                    $id,
                    $userId,
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
        ['id' => $publicationId, 'ignoreRoi' => $ignoreRoi] = self::readPublicationData($data);

        $data->setValue([
            'id' => $publicationId,
            'url' => $config['clientUrl'].'/'.$publicationId,
            'editUrl' => $config['clientUrl'].'/publications/'.$publicationId.'/edit',
            'ignoreRoi' => $ignoreRoi,
        ]);
        $data->setName(self::DATA_PUBLICATION);
    }

    /**
     * The publication of a basket synchronization: stored as JSON, or as the bare publication ID
     * by the synchronizations created before the option to publish the whole files.
     *
     * @return array{id: string, ignoreRoi: bool}
     */
    public static function readPublicationData(IntegrationData $data): array
    {
        $value = $data->getValue();
        if (is_string($value) && str_starts_with($value, '{')) {
            $decoded = json_decode($value, true);
            if (is_array($decoded) && is_string($decoded['id'] ?? null)) {
                return [
                    'id' => $decoded['id'],
                    'ignoreRoi' => true === ($decoded['ignoreRoi'] ?? false),
                ];
            }
        }

        return [
            'id' => (string) $value,
            'ignoreRoi' => false,
        ];
    }

    public function supportData(string $integrationName, string $dataName, IntegrationConfig $config): bool
    {
        return $integrationName === static::getName() && self::DATA_PUBLICATION_ID === $dataName;
    }

    #[\Override]
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

    #[\Override]
    public function getConfigurationInfo(IntegrationConfig $config): array
    {
        return [
            [
                'label' => 'Redirect URI',
                'description' => 'The URI that must be allowed in the OAuth client configuration of Expose.',
                'value' => $this->urlGenerator->generate('integration_auth_code', [
                    'integrationId' => $config->getIntegrationId(),
                ], UrlGeneratorInterface::ABSOLUTE_URL),
            ],
        ];
    }

    public static function getDisplayName(): string
    {
        return 'Expose';
    }

    public static function getCategories(): array
    {
        return [
            IntegrationCategory::Publication,
        ];
    }

    public static function getName(): string
    {
        return self::NAME;
    }

    #[\Override]
    public function getSupportedContexts(): array
    {
        return [IntegrationContext::Basket, IntegrationContext::Automation];
    }
}
