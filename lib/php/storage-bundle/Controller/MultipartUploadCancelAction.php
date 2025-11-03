<?php

declare(strict_types=1);

namespace Alchemy\StorageBundle\Controller;

use Alchemy\StorageBundle\Entity\MultipartUpload;
use Alchemy\StorageBundle\Upload\UploadManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;

class MultipartUploadCancelAction extends AbstractController
{
    public function __construct(private UploadManager $uploadManager, private EntityManagerInterface $em)
    {
    }

    public function __invoke(MultipartUpload $data, Request $request)
    {
        try {
            $this->uploadManager->cancelMultipartUpload($data->getPath(), $data->getUploadId());
        } catch (\Throwable $e) {
            // S3 storage will clean up its uncomplete uploads automatically
        }

        $this->em->remove($data);
    }
}
