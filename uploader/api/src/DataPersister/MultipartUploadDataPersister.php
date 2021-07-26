<?php

declare(strict_types=1);

namespace App\DataPersister;

use ApiPlatform\Core\DataPersister\ContextAwareDataPersisterInterface;
use ApiPlatform\Core\DataPersister\DataPersisterInterface;
use App\Entity\MultipartUpload;
use App\Storage\FileStorageManager;
use App\Upload\UploadManager;

class MultipartUploadDataPersister implements ContextAwareDataPersisterInterface
{
    private DataPersisterInterface $decorated;
    private FileStorageManager $storageManager;
    private UploadManager $uploadManager;

    public function __construct(
        DataPersisterInterface $decorated,
        FileStorageManager $storageManager,
        UploadManager $uploadManager
    ) {
        $this->decorated = $decorated;
        $this->storageManager = $storageManager;
        $this->uploadManager = $uploadManager;
    }

    public function supports($data, array $context = []): bool
    {
        return $this->decorated->supports($data, $context);
    }

    public function persist($data, array $context = [])
    {
        if ($data instanceof MultipartUpload) {
            $extension = pathinfo($data->getFilename(), PATHINFO_EXTENSION);
            $path = $this->storageManager->generatePath($extension);

            $uploadData = $this->uploadManager->prepareMultipartUpload($path, $data->getType());
            $data->setUploadId($uploadData->get('UploadId'));
            $data->setPath($path);
        }

        $this->decorated->persist($data);

        return $data;
    }

    public function remove($data, array $context = [])
    {
        $this->decorated->remove($data, $context);
    }
}
