<?php

declare(strict_types=1);

namespace Alchemy\StorageBundle\Controller;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Alchemy\StorageBundle\Upload\UploadManager;
use Alchemy\StorageBundle\Entity\MultipartUpload;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

final class MultipartUploadCompleteAction extends AbstractController
{
    public function __construct(private UploadManager $uploadManager, private EntityManagerInterface $em,)
    {
    }
    
    public function __invoke(MultipartUpload $data, Request $request)
    {
        $parts = $request->request->all('parts');

        if (empty($parts)) {
            throw new BadRequestHttpException('Missing parts');
        }

        $res = $this->uploadManager->markComplete($data->getUploadId(), $data->getPath(), (array) $parts);

        $data->setComplete(true);
        $this->em->persist($data);
        $this->em->flush();

        return new JsonResponse([
            'path' => $res['Key'],
        ]);
    }
}
