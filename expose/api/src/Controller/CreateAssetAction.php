<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Asset;
use App\Entity\MultipartUpload;
use App\Security\Voter\PublicationVoter;
use App\Storage\AssetManager;
use App\Storage\FileStorageManager;
use App\Upload\UploadManager;
use Mimey\MimeTypes;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

final class CreateAssetAction extends AbstractController
{
    private FileStorageManager $storageManager;
    private AssetManager $assetManager;
    private UploadManager $uploadManager;
    private EntityManagerInterface $em;

    public function __construct(
        FileStorageManager $storageManager,
        AssetManager $assetManager,
        UploadManager $uploadManager,
        EntityManagerInterface $em
    ) {
        $this->storageManager = $storageManager;
        $this->assetManager = $assetManager;
        $this->uploadManager = $uploadManager;
        $this->em = $em;
    }

    public function __invoke(Request $request): Asset
    {
        if (!$request->request->get('publication_id')) {
            // If no publication is assigned, we validate the following grant:
            $this->denyAccessUnlessGranted(PublicationVoter::CREATE);
        }

        if (null !== $request->request->get('multipart')) {
            return $this->handleMultipartUpload($request);
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
            $path = $this->storageManager->generatePath($extension);

            $stream = fopen($uploadedFile->getRealPath(), 'r+');
            $this->storageManager->storeStream($path, $stream);
            fclose($stream);

            return $this->assetManager->createAsset(
                $path,
                $uploadedFile->getMimeType(),
                $uploadedFile->getClientOriginalName(),
                $uploadedFile->getSize(),
                $request->request->all()
            );
        } elseif (null !== $upload = $request->request->get('upload')) {
            if (!is_array($upload)) {
                throw new BadRequestHttpException('"upload" must be an array');
            }

            $originalFilename = $upload['name'] ?? null;
            $contentType = $upload['type'] ?? null;
            if (null === $contentType && !empty($originalFilename)) {
                $extension = pathinfo($originalFilename, PATHINFO_EXTENSION);
                $contentType = (new MimeTypes())->getMimeType($extension);
            }

            $contentType ??= 'application/octet-stream';

            if (null === $originalFilename) {
                $extension = (new MimeTypes())->getExtension($contentType);
            } else {
                $extension = pathinfo($originalFilename, PATHINFO_EXTENSION);
            }
            $path = $this->storageManager->generatePath($extension);

            $asset = $this->assetManager->createAsset(
                $path,
                $contentType,
                $originalFilename ?? 'file',
                (int) ($upload['size'] ?? 0),
                $request->request->all()
            );

            $url = $this->uploadManager->createPutObjectSignedURL($path, $contentType);
            $asset->setUploadURL($url);

            return $asset;
        } else {
            throw new BadRequestHttpException('Missing file or contentType');
        }
    }

    private function handleMultipartUpload(Request $request): Asset
    {
        $multipart = $request->request->get('multipart');

        foreach ([
                     'parts',
                     'uploadId',
                 ] as $key) {
            if (empty($multipart[$key])) {
                throw new BadRequestHttpException(sprintf('Missing multipart param: %s', $key));
            }
        }

        $multipartUpload = $this->em->getRepository(MultipartUpload::class)->find($multipart['uploadId']);
        if (!$multipartUpload instanceof MultipartUpload) {
            throw new BadRequestHttpException();
        }

        $this->uploadManager->markComplete(
            $multipartUpload->getUploadId(),
            $multipartUpload->getPath(),
            $multipart['parts']
        );

        $multipartUpload->setComplete(true);
        $this->em->persist($multipartUpload);

        return $this->assetManager->createAsset(
            $multipartUpload->getPath(),
            $multipartUpload->getType(),
            $multipartUpload->getFilename(),
            $multipartUpload->getSize(),
            $request->request->all(),
        );
    }
}
