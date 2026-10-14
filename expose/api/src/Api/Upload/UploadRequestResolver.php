<?php

declare(strict_types=1);

namespace App\Api\Upload;

use Alchemy\StorageBundle\Api\Dto\MultipartUploadInput;
use Alchemy\StorageBundle\Storage\FileStorageManager;
use Alchemy\StorageBundle\Storage\PathGeneratorInterface;
use Alchemy\StorageBundle\Upload\UploadManager;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Mime\MimeTypes;

/**
 * Resolves the file carried by an asset or sub-definition creation request.
 *
 * Three shapes are accepted: a completed multipart upload ("multipart"), a file
 * sent with the request ("file") or a file descriptor ("upload") for which a
 * signed PUT URL is generated.
 */
final readonly class UploadRequestResolver
{
    public function __construct(
        private FileStorageManager $storageManager,
        private UploadManager $uploadManager,
        private PathGeneratorInterface $pathGenerator,
    ) {
    }

    public function resolve(Request $request): ResolvedUpload
    {
        if ($request->request->has('multipart')) {
            $multipartUpload = $this->uploadManager->handleMultipartUpload(MultipartUploadInput::fromRequest($request));

            return new ResolvedUpload(
                $multipartUpload->getPath(),
                $multipartUpload->getType(),
                $multipartUpload->getFilename(),
                $multipartUpload->getSize(),
            );
        }

        /** @var UploadedFile|null $uploadedFile */
        $uploadedFile = $request->files->get('file');
        if (null !== $uploadedFile) {
            ini_set('max_execution_time', '600');

            if (!$uploadedFile->isValid()) {
                throw new BadRequestHttpException('Invalid uploaded file');
            }
            if (0 === $uploadedFile->getSize()) {
                throw new BadRequestHttpException('Empty file');
            }

            $extension = pathinfo($uploadedFile->getClientOriginalName(), PATHINFO_EXTENSION);
            $path = $this->pathGenerator->generatePath($extension);

            $stream = fopen($uploadedFile->getRealPath(), 'r+');
            $this->storageManager->storeStream($path, $stream);
            fclose($stream);

            return new ResolvedUpload(
                $path,
                $uploadedFile->getMimeType(),
                $uploadedFile->getClientOriginalName(),
                $uploadedFile->getSize(),
            );
        }

        if ($request->request->has('upload')) {
            $upload = $request->request->all('upload');

            $originalFilename = $upload['name'] ?? null;
            $contentType = $upload['type'] ?? null;
            if (null === $contentType && !empty($originalFilename)) {
                $extension = pathinfo((string) $originalFilename, PATHINFO_EXTENSION);
                $contentType = (new MimeTypes())->getMimeTypes($extension)[0];
            }

            $contentType ??= 'application/octet-stream';

            if (null === $originalFilename) {
                $extension = (new MimeTypes())->getExtensions($contentType)[0];
            } else {
                $extension = pathinfo((string) $originalFilename, PATHINFO_EXTENSION);
            }
            $path = $this->pathGenerator->generatePath($extension);

            return new ResolvedUpload(
                $path,
                $contentType,
                $originalFilename ?? 'file',
                (int) ($upload['size'] ?? 0),
                $this->uploadManager->createPutObjectSignedURL($path, $contentType),
            );
        }

        throw new BadRequestHttpException('Missing file or contentType');
    }
}
