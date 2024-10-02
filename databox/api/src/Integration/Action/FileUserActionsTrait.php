<?php

declare(strict_types=1);

namespace App\Integration\Action;

use Alchemy\CoreBundle\Util\DoctrineUtil;
use Alchemy\StorageBundle\Upload\UploadManager;
use App\Asset\FileUrlResolver;
use App\Entity\Core\Asset;
use App\Entity\Core\File;
use App\Entity\Integration\IntegrationData;
use App\Http\FileUploadManager;
use App\Integration\IntegrationConfig;
use App\Security\Voter\AbstractVoter;
use App\Storage\FileManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Contracts\Service\Attribute\Required;

trait FileUserActionsTrait
{
    use UserActionsTrait;

    final public const DATA_FILE_ID = 'file_id';
    final public const DATA_FILE = 'file';

    protected FileManager $fileManager;
    protected UploadManager $uploadManager;
    protected FileUrlResolver $fileUrlResolver;

    protected function getFile(Request $request): File
    {
        $fileId = $request->request->get('fileId');
        $file = DoctrineUtil::findStrict($this->em, File::class, $fileId);
        $this->denyAccessUnlessGranted(AbstractVoter::EDIT, $file);

        return $file;
    }

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

        $multipartUpload = $this->uploadManager->handleMultipartUpload($request);

        return $this->fileManager->createFileFromMultipartUpload($multipartUpload, $asset->getWorkspace());
    }

    public function transformData(IntegrationData $data, IntegrationConfig $config): void
    {
        $file = $this->em->find(File::class, $data->getValue());
        $data->setValue([
            'id' => $file->getId(),
            'url' => $this->fileUrlResolver->resolveUrl($file),
        ]);
        $data->setName(self::DATA_FILE);
    }

    public function supportData(string $integrationName, string $dataName, IntegrationConfig $config): bool
    {
        return $integrationName === static::getName() && self::DATA_FILE_ID === $dataName;
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
    public function setUploadManager(UploadManager $uploadManager): void
    {
        $this->uploadManager = $uploadManager;
    }
}
