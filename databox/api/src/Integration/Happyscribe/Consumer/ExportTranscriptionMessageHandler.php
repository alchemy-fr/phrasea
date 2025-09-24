<?php

declare(strict_types=1);

namespace App\Integration\Happyscribe\Consumer;

use App\Api\Model\Input\Attribute\AssetAttributeBatchUpdateInput;
use App\Api\Model\Input\Attribute\AttributeActionInput;
use App\Attribute\BatchAttributeManager;
use App\Entity\Core\Attribute;
use App\Integration\Happyscribe\HappyscribeIntegration;
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
    ) {
    }

    public function __invoke(ExportTranscriptionMessage $message): void
    {
        $transcriptionId = $message->getTranscriptionId();
        $config = json_decode($message->getConfig(), true, 512, JSON_THROW_ON_ERROR);
        $happyscribeToken = $config['apiKey'];
        $exportId = $config['exportId'];

        $resCheckExport = $this->happyscribeClient->request('GET', 'https://www.happyscribe.com/api/v1/exports/'.$exportId, [
            'headers' => [
                'Authorization' => 'Bearer '.$happyscribeToken,
            ],
        ]);

        if (200 !== $resCheckExport->getStatusCode()) {
            throw new \RuntimeException('error when checking transcript export, response status : '.$resCheckExport->getStatusCode());
        }

        $resCheckExportBody = $resCheckExport->toArray();

        $exportStatus = $resCheckExportBody['state'];
        if (isset($resCheckExportBody['failureMessage'])) {
            $failureExportMessage = $resCheckExportBody['failureMessage'];
        }

        if (!in_array($exportStatus, ['ready', 'expired', 'failed'])) {
            $delay = (int) (3 * $message->getDelay());

            $this->bus->dispatch(new ExportTranscriptionMessage($transcriptionId, $message->getConfig(), $delay), [new DelayStamp($delay)]);

            return;
        }

        if ('ready' != $exportStatus) {
            throw new \RuntimeException('exporting transcript failed, status : '.$exportStatus.', message : '.$failureExportMessage);
        }

        $res = $this->happyscribeClient->request('GET', $resCheckExportBody['download_link']);

        $transcriptionContent = $res->getContent();

        $input = new AssetAttributeBatchUpdateInput();

        $i = new AttributeActionInput();
        $i->definitionId = $config['attributeId'];
        $i->origin = Attribute::ORIGIN_MACHINE;
        $i->originVendor = HappyscribeIntegration::getName();
        $i->value = $transcriptionContent;

        if ($config['isTranslatableAttribute'] && !empty($config['locale'])) {
            $i->locale = $config['locale'];
        }

        $input->actions[] = $i;

        try {
            $this->batchAttributeManager->handleBatch(
                $config['workspaceId'],
                [$config['assetId']],
                $input,
                null
            );
        } catch (BadRequestHttpException $e) {
            throw new \InvalidArgumentException($e->getMessage(), previous: $e);
        }
    }
}
