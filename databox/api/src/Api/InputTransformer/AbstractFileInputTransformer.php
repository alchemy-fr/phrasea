<?php

declare(strict_types=1);

namespace App\Api\InputTransformer;

use Alchemy\MessengerBundle\Listener\PostFlushStack;
use Alchemy\StorageBundle\Upload\UploadManager;
use Alchemy\StorageBundle\Util\FileUtil;
use App\Api\Model\Input\AssetInput;
use App\Api\Model\Input\AssetSourceInput;
use App\Consumer\Handler\File\ImportFile;
use App\Entity\Core\File;
use App\Entity\Core\Workspace;
use App\Http\FileUploadManager;
use App\Service\Storage\RenditionManager;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Contracts\Service\Attribute\Required;

abstract class AbstractFileInputTransformer extends AbstractInputTransformer
{
    protected PostFlushStack $postFlushStackListener;
    protected RenditionManager $renditionManager;
    private UploadManager $uploadManager;
    private FileUploadManager $fileUploadManager;
    private RequestStack $requestStack;

    protected function handleFromFile(?string $fileId): ?File
    {
        if (null === $fileId) {
            return null;
        }

        $file = $this->getEntity(File::class, $fileId);
        if (!$file->isPathPublic()) {
            throw new BadRequestHttpException(sprintf('Copy error: File "%s" has a private path', $fileId));
        }

        return $file;
    }

    protected function handleUpload(Workspace $workspace, AssetInput $assetInput): ?File
    {
        if ($assetInput->multipart) {
            $multipartUpload = $this->uploadManager->handleMultipartUpload($assetInput->multipart);

            $file = new File();
            $file->setWorkspace($workspace);
            $file->setStorage(File::STORAGE_S3_MAIN);
            $file->setType($multipartUpload->getType());
            $file->setExtension(FileUtil::guessExtension($multipartUpload->getType(), $multipartUpload->getFilename()));
            $file->setSize($multipartUpload->getSize());
            $file->setOriginalName($multipartUpload->getFilename());
            $file->setPath($multipartUpload->getPath());

            return $file;
        }

        if (null === $request = $this->requestStack->getCurrentRequest()) {
            return null;
        }

        /** @var UploadedFile|null $uploadedFile */
        $uploadedFile = $request->files->get('file');
        if (null !== $uploadedFile) {
            return $this->fileUploadManager->storeUploadedFile($workspace, $uploadedFile);
        }

        return null;
    }

    protected function handleSource(?AssetSourceInput $source, Workspace $workspace): ?File
    {
        if (null === $source) {
            return null;
        }

        $file = new File();
        $file->setPath($source->url);
        $file->setOriginalName($source->originalName);
        $extension = FileUtil::getExtensionFromPath($source->originalName ?: $source->url);
        $file->setExtension($extension);
        $file->setType($source->type ?? FileUtil::getTypeFromExtension($extension));
        $file->setPathPublic(!$source->isPrivate);
        $file->setStorage(File::STORAGE_URL);
        $file->setWorkspace($workspace);

        if (null !== $source->alternateUrls) {
            foreach ($source->alternateUrls as $altUrl) {
                $file->setAlternateUrl($altUrl->type, $altUrl->url);
            }
        }

        $this->em->persist($file);

        if ($source->importFile) {
            $this->postFlushStackListener->addBusMessage(new ImportFile($file->getId()));
        }

        return $file;
    }

    #[Required]
    public function setRenditionManager(RenditionManager $renditionManager): void
    {
        $this->renditionManager = $renditionManager;
    }

    #[Required]
    public function setUploadManager(UploadManager $uploadManager): void
    {
        $this->uploadManager = $uploadManager;
    }

    #[Required]
    public function setFileUploadManager(FileUploadManager $fileUploadManager): void
    {
        $this->fileUploadManager = $fileUploadManager;
    }

    #[Required]
    public function setPostFlushStackListener(PostFlushStack $postFlushStackListener): void
    {
        $this->postFlushStackListener = $postFlushStackListener;
    }

    #[Required]
    public function setRequestStack(RequestStack $requestStack): void
    {
        $this->requestStack = $requestStack;
    }
}
