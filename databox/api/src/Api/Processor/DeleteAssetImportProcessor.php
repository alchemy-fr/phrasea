<?php

declare(strict_types=1);

namespace App\Api\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Core\AssetImport;
use App\Service\Import\AssetImportManager;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Deletes the import tracking and its instructions file, never the created assets.
 */
final readonly class DeleteAssetImportProcessor implements ProcessorInterface
{
    public function __construct(
        private AssetImportManager $importManager,
        #[Autowire(service: 'api_platform.doctrine.orm.state.remove_processor')]
        private ProcessorInterface $removeProcessor,
    ) {
    }

    /**
     * @param AssetImport $data
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        $this->importManager->scheduleFileDeletion($data);

        return $this->removeProcessor->process($data, $operation, $uriVariables, $context);
    }
}
