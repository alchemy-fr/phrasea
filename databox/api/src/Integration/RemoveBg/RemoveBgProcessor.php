<?php

declare(strict_types=1);

namespace App\Integration\RemoveBg;

use App\Entity\Core\File;
use App\Entity\Integration\WorkspaceIntegration;
use App\Integration\ApiBudgetLimiter;
use App\Integration\FileActionsIntegrationInterface;
use App\Integration\IntegrationDataManager;
use App\Storage\FileManager;
use Doctrine\ORM\EntityManagerInterface;

final readonly class RemoveBgProcessor
{
    public function __construct(
        private RemoveBgClient $client,
        private ApiBudgetLimiter $apiBudgetLimiter,
        private IntegrationDataManager $integrationDataManager,
        private FileManager $fileManager,
        private EntityManagerInterface $em,
    ) {
    }

    public function process(File $file, array $config): File
    {
        $this->apiBudgetLimiter->acceptIntegrationApiCall($config);

        $src = $this->client->getBgRemoved($file, $config['apiKey']);

        $bgRemFile = $this->fileManager->createFileFromPath(
            $file->getWorkspace(),
            $src,
            'image/png',
            'png',
            sprintf('%s-bg-removed.png', $file->getOriginalName() ?? $file->getId())
        );

        /** @var WorkspaceIntegration $wsIntegration */
        $wsIntegration = $config['workspaceIntegration'];
        $this->integrationDataManager->storeData($wsIntegration, $file, FileActionsIntegrationInterface::DATA_FILE_ID, $bgRemFile->getId());

        $this->em->flush();

        return $bgRemFile;
    }
}
