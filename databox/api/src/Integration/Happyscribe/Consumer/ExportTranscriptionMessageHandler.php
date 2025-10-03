<?php

declare(strict_types=1);

namespace App\Integration\Happyscribe\Consumer;

use App\Api\Model\Input\Attribute\AssetAttributeBatchUpdateInput;
use App\Api\Model\Input\Attribute\AttributeActionInput;
use App\Attribute\BatchAttributeManager;
use App\Entity\Core\Asset;
use App\Entity\Core\Attribute;
use App\Integration\Happyscribe\HappyscribeIntegration;
use App\Integration\IntegrationManager;
use App\Repository\Core\AttributeDefinitionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DelayStamp;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[AsMessageHandler]
final readonly class ExportTranscriptionMessageHandler
{
    public function __construct(
        private MessageBusInterface $bus,
        private HttpClientInterface $happyscribeClient,
        private readonly BatchAttributeManager $batchAttributeManager,
        private IntegrationManager $integrationManager,
        private readonly AttributeDefinitionRepository $attributeDefinitionRepository,
        private EntityManagerInterface $em,
    ) {
    }

    public function __invoke(ExportTranscriptionMessage $message): void
    {
        $integrationId = $message->getIntegrationId();

        $integration = $this->integrationManager->loadIntegration($integrationId) ?? throw new \RuntimeException('Integration not found: '.$integrationId);

        $integrationConfig = $this->integrationManager->getIntegrationConfiguration($integration);

        $asset = $this->em->find(Asset::class, $message->getAssetId());

        $happyscribeToken = $integrationConfig['apiKey'];
        $exportId = $message->getExportId();
        $failureExportMessage = '';

        $resCheckExport = $this->happyscribeClient->request('GET', 'https://www.happyscribe.com/api/v1/exports/'.$exportId, [
            'headers' => [
                'Authorization' => 'Bearer '.$happyscribeToken,
            ],
        ]);

        if (200 !== $resCheckExport->getStatusCode()) {
            throw new \RuntimeException('Error when checking transcript export, response status: '.$resCheckExport->getStatusCode());
        }

        $resCheckExportBody = $resCheckExport->toArray();

        $exportStatus = $resCheckExportBody['state'];
        if (isset($resCheckExportBody['failureMessage'])) {
            $failureExportMessage = $resCheckExportBody['failureMessage'];
        }

        if (!in_array($exportStatus, ['ready', 'expired', 'failed'], true)) {
            $retryNumber = $message->getRetry();
            $delays = [3, 5, 10, 30, 60, 120];
            $delay = $delays[$retryNumber] ?? 200;

            $delay = $delay * 1000;

            $this->bus->dispatch(new ExportTranscriptionMessage($exportId, $integrationId, $message->getAssetId(), $message->getLocale(), $retryNumber + 1), [new DelayStamp($delay)]);

            return;
        }

        if ('ready' !== $exportStatus) {
            throw new \RuntimeException('Exporting transcript failed, status: '.$exportStatus.', message : '.$failureExportMessage);
        }

        $res = $this->happyscribeClient->request('GET', $resCheckExportBody['download_link']);

        $transcriptionContent = $res->getContent();

        $attrDef = $this->attributeDefinitionRepository
                ->getAttributeDefinitionBySlug($asset->getWorkspaceId(), $integrationConfig['attribute'])
                    ?? throw new \InvalidArgumentException(sprintf('Attribute definition slug "%s" not found in workspace "%s"', $integrationConfig['attribute'], $asset->getWorkspaceId()));

        $input = new AssetAttributeBatchUpdateInput();

        $i = new AttributeActionInput();
        $i->definitionId = $attrDef->getId();
        $i->origin = Attribute::ORIGIN_MACHINE;
        $i->originVendor = HappyscribeIntegration::getName();
        $i->value = $transcriptionContent;

        if ($attrDef->isTranslatable() && null !== $message->getLocale()) {
            $i->locale = $message->getLocale();
        }

        $input->actions[] = $i;

        try {
            $this->batchAttributeManager->handleBatch(
                $asset->getWorkspaceId(),
                [$message->getAssetId()],
                $input,
                null
            );
        } catch (BadRequestHttpException $e) {
            throw new \InvalidArgumentException($e->getMessage(), previous: $e);
        }
    }
}
