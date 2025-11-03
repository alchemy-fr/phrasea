<?php

declare(strict_types=1);

namespace App\Api\InputTransformer;

use Alchemy\MessengerBundle\Listener\PostFlushStack;
use Alchemy\StorageBundle\Api\Dto\MultipartUploadInput;
use Alchemy\StorageBundle\Upload\UploadManager;
use Alchemy\StorageBundle\Util\FileUtil;
use App\Api\Model\Input\FileSourceInput;
use App\Border\FileAnalyzer;
use App\Consumer\Handler\File\AnalyzeFile;
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
    protected FileAnalyzer $fileAnalyzer;

    protected function handleFromFile(?string $fileId, Workspace $workspace): ?File
    {
        if (null === $fileId) {
            return null;
        }

        $file = $this->getEntity(File::class, $fileId);
        if ($file->getWorkspaceId() !== $workspace->getId()) {
            throw new BadRequestHttpException(sprintf('Copy error: File "%s" does not belong to workspace "%s"', $fileId, $workspace->getId()));
        }

        return $file;
    }

    protected function handleUpload(?MultipartUploadInput $multipart, Workspace $workspace): ?File
    {
        if ($multipart) {
            $multipartUpload = $this->uploadManager->handleMultipartUpload($multipart);

            $file = new File();
            $file->setWorkspace($workspace);
            $file->setStorage(File::STORAGE_S3_MAIN);
            $file->setType($multipartUpload->getType());
            $file->setExtension(FileUtil::guessExtension($multipartUpload->getType(), $multipartUpload->getFilename()));
            $file->setSize($multipartUpload->getSize());
            $file->setOriginalName($multipartUpload->getFilename());
            $file->setPath($multipartUpload->getPath());
            $this->normalizeFile($file);

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

    protected function handleSource(?FileSourceInput $source, Workspace $workspace): ?File
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
        $this->normalizeFile($file);

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

    private function normalizeFile(File $file): void
    {
        if ($this->fileAnalyzer->preAnalyzeFile($file)) {
            $this->postFlushStackListener->addBusMessage(new AnalyzeFile($file->getId()));
        }
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

    #[Required]
    public function setFileAnalyzer(FileAnalyzer $fileAnalyzer): void
    {
        $this->fileAnalyzer = $fileAnalyzer;
    }
}
