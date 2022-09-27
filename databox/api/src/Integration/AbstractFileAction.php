<?php

declare(strict_types=1);

namespace App\Integration;

use App\Asset\FileUrlResolver;
use App\Entity\Core\Asset;
use App\Entity\Core\File;
use App\Entity\Integration\IntegrationData;
use App\Storage\FileManager;
use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

abstract class AbstractFileAction extends AbstractIntegration implements FileActionsIntegrationInterface, IntegrationDataTransformerInterface
{
    protected const DATA_FILE_ID = 'file_id';
    protected const DATA_FILE_URL = 'file_url';
    protected FileManager $fileManager;
    protected EntityManagerInterface $em;
    protected IntegrationDataManager $integrationDataManager;
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
            throw new InvalidArgumentException('Missing or invalid file');
        }

        return $this->fileManager->createFileFromPath(
            $asset->getWorkspace(),
            $file->getRealPath(),
            $file->getType(),
            $file->getClientOriginalExtension(),
            $file->getClientOriginalName()
        );
    }

    public function transformData(IntegrationData $data): void
    {
        $file = $this->em->find(File::class, $data->getValue());
        $data->setValue($this->fileUrlResolver->resolveUrl($file));
        $data->setName(self::DATA_FILE_URL);
    }

    public function supportData(string $integrationName, string $dataKey): bool
    {
        return $integrationName === static::getName() && $dataKey === self::DATA_FILE_ID;
    }

    /**
     * @required
     */
    public function setFileManager(FileManager $fileManager): void
    {
        $this->fileManager = $fileManager;
    }

    /**
     * @required
     */
    public function setEm(EntityManagerInterface $em): void
    {
        $this->em = $em;
    }

    /**
     * @required
     */
    public function setIntegrationDataManager(IntegrationDataManager $integrationDataManager): void
    {
        $this->integrationDataManager = $integrationDataManager;
    }

    /**
     * @required
     */
    public function setFileUrlResolver(FileUrlResolver $fileUrlResolver): void
    {
        $this->fileUrlResolver = $fileUrlResolver;
    }
}
