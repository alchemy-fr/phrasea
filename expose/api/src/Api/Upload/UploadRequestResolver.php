<?php

declare(strict_types=1);

namespace App\Api\Upload;

use Alchemy\StorageBundle\Api\Dto\MultipartUploadInput;
use Alchemy\StorageBundle\Storage\FileStorageManager;
use Alchemy\StorageBundle\Storage\PathGeneratorInterface;
use Alchemy\StorageBundle\Upload\FileValidator;
use Alchemy\StorageBundle\Upload\UploadManager;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Mime\MimeTypes;

/**
 * Resolves the file carried by an asset or sub-definition creation request.
 *
 * Three shapes are accepted: a completed multipart upload ("multipart"), a file
 * sent with the request ("file") or a file descriptor ("upload") for which a
 * signed PUT URL is generated. The (type, extension) couple of the last two is
 * checked against the allowed file types (the multipart upload checked it when
 * it was created), and the content of the file sent with the request too. The
 * content of a completed multipart upload is checked when it is completed, the
 * one sent to a signed PUT URL once the URL expired (CheckPresignedUpload).
 */
final readonly class UploadRequestResolver
{
    public function __construct(
        private FileStorageManager $storageManager,
        private UploadManager $uploadManager,
        private PathGeneratorInterface $pathGenerator,
        private Security $security,
        private FileValidator $fileValidator,
    ) {
    }

    public function resolve(Request $request): ResolvedUpload
    {
        if ($request->request->has('multipart')) {
            $ownerId = $this->security->getUser()?->getUserIdentifier()
                ?? throw new AccessDeniedHttpException('User must be authenticated');
            $multipartUpload = $this->uploadManager->handleMultipartUpload(MultipartUploadInput::fromRequest($request), $ownerId);

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

            $this->fileValidator->validateFileContent(
                $uploadedFile->getRealPath(),
                $uploadedFile->getClientOriginalName(),
                $uploadedFile->getMimeType(),
            );

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

            $originalFilename = self::getString($upload, 'name');
            $contentType = self::getString($upload, 'type');
            if (null === $contentType && null !== $originalFilename) {
                $extension = pathinfo($originalFilename, PATHINFO_EXTENSION);
                $contentType = (new MimeTypes())->getMimeTypes($extension)[0] ?? null;
            }

            $contentType ??= 'application/octet-stream';

            if (null === $originalFilename) {
                $extension = (new MimeTypes())->getExtensions($contentType)[0] ?? null;
            } else {
                $extension = pathinfo($originalFilename, PATHINFO_EXTENSION);
            }
            $path = $this->pathGenerator->generatePath('' === $extension ? null : $extension);

            return new ResolvedUpload(
                $path,
                $contentType,
                $originalFilename ?? 'file',
                is_numeric($upload['size'] ?? null) ? (int) $upload['size'] : 0,
                $this->uploadManager->createPutObjectSignedURL($path, $contentType),
            );
        }

        throw new BadRequestHttpException('Missing file or contentType');
    }

    private static function getString(array $upload, string $key): ?string
    {
        $value = $upload[$key] ?? null;
        if (null !== $value && !is_string($value)) {
            throw new BadRequestHttpException(sprintf('"upload.%s" must be a string', $key));
        }

        return '' === $value ? null : $value;
    }
}
