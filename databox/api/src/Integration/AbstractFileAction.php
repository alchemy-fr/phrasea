<?php

declare(strict_types=1);

namespace App\Integration;

use App\Asset\FileUrlResolver;
use App\Entity\Core\Asset;
use App\Entity\Core\File;
use App\Entity\Integration\AbstractIntegrationData;
use App\Entity\Integration\IntegrationFileData;
use App\Http\FileUploadManager;
use App\Storage\FileManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Contracts\Service\Attribute\Required;

abstract class AbstractFileAction extends AbstractActionIntegration implements FileActionsIntegrationInterface, IntegrationDataTransformerInterface
{
    protected FileManager $fileManager;
    protected FileUploadManager $fileUploadManager;
    protected EntityManagerInterface $em;
    protected FileUrlResolver $fileUrlResolver;

    protected function saveFile(File $parentFile, Request $request): File
    {
        $assetId = $request->request->get('assetId');
        if (!$assetId) {
            throw new BadRequestHttpException('Missing assetId');
        }
        $asset = $this->em->getRepository(Asset::class)
            ->find($assetId);
        if (!$asset instanceof Asset) {
            throw new BadRequestHttpException(sprintf('Asset "%s" not found', $assetId));
        }
        if ($asset->getWorkspaceId() !== $parentFile->getWorkspaceId()) {
            throw new BadRequestHttpException(sprintf('File "%s" and Asset "%s" are not from the same workspace', $parentFile->getId(), $asset->getId()));
        }

        $file = $request->files->get('file');
        if (!$file instanceof UploadedFile) {
            throw new \InvalidArgumentException('Missing or invalid file');
        }

        return $this->fileUploadManager->storeFileUploadFromRequest($asset->getWorkspace(), $file);
    }

    /**
     * @param IntegrationFileData $data
     */
    public function transformData(AbstractIntegrationData $data, IntegrationConfig $config): void
    {
        $file = $this->em->find(File::class, $data->getValue());
        $data->setValue([
            'id' => $file->getId(),
            'url' => $this->fileUrlResolver->resolveUrl($file),
        ]);
        $data->setName(FileActionsIntegrationInterface::DATA_FILE);
    }

    public function supportData(string $integrationName, string $dataName, IntegrationConfig $config): bool
    {
        return $integrationName === static::getName() && FileActionsIntegrationInterface::DATA_FILE_ID === $dataName;
    }

    #[Required]
    public function setFileManager(FileManager $fileManager): void
    {
        $this->fileManager = $fileManager;
    }

    #[Required]
    public function setEm(EntityManagerInterface $em): void
    {
        $this->em = $em;
    }

    #[Required]
    public function setFileUrlResolver(FileUrlResolver $fileUrlResolver): void
    {
        $this->fileUrlResolver = $fileUrlResolver;
    }

    #[Required]
    public function setFileUploadManager(FileUploadManager $fileUploadManager): void
    {
        $this->fileUploadManager = $fileUploadManager;
    }
}
