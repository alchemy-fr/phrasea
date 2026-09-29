<?php

declare(strict_types=1);

namespace Alchemy\StorageBundle\Controller;

use Alchemy\StorageBundle\Entity\MultipartUpload;
use Alchemy\StorageBundle\Upload\MultipartUploadPlanner;
use Alchemy\StorageBundle\Upload\UploadManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * Hands out the presigned URLs of the parts still to upload, from a given
 * part number to the last one. Serves both resuming an upload and refreshing
 * URLs that expired during a long transfer.
 */
final class MultipartUploadPartsAction extends AbstractController
{
    public function __construct(
        private readonly UploadManager $uploadManager,
        private readonly MultipartUploadPlanner $planner,
    ) {
    }

    public function __invoke(MultipartUpload $data, Request $request): JsonResponse
    {
        if ($data->isComplete()) {
            throw new BadRequestHttpException('Upload is already complete');
        }

        $from = $request->request->get('from', 1);
        if (!is_int($from) && !(is_string($from) && ctype_digit($from))) {
            throw new BadRequestHttpException('"from" must be a positive integer');
        }
        $from = (int) $from;

        // Uploads created before the chunk size was persisted fall back to the current policy.
        $chunkSize = $data->getChunkSize() ?? $this->planner->resolveChunkSize($data->getSize());
        $partCount = $this->planner->getPartCount($data->getSize(), $chunkSize);

        if ($from < 1) {
            throw new BadRequestHttpException('"from" must be greater than or equal to 1');
        }

        return new JsonResponse([
            'chunkSize' => $chunkSize,
            'partCount' => $partCount,
            // Past the last part there is nothing left to upload (e.g. resuming right before completion)
            'urls' => $from <= $partCount
                ? $this->uploadManager->getSignedUrls($data->getUploadId(), $data->getPath(), $from, $partCount)
                : [],
        ]);
    }
}
