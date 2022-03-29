<?php

declare(strict_types=1);

namespace App\Controller\Core;

use Alchemy\StorageBundle\Storage\FileStorageManager;
use Alchemy\StorageBundle\Storage\PathGenerator;
use App\Entity\Core\Asset;
use App\Entity\Core\File;
use App\Security\JWTTokenManager;
use App\Storage\RenditionManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;

class PhraseanetController extends AbstractController
{
    /**
     * @Route(path="/renditions/incoming/{assetId}", name="phraseanet_incoming_rendition")
     */
    public function incomingRenditionAction(
        string $assetId,
        Request $request,
        RenditionManager $renditionManager,
        PathGenerator $pathGenerator,
        FileStorageManager $storageManager,
        JWTTokenManager $JWTTokenManager,
        EntityManagerInterface $em
    ): Response {
        ini_set('max_execution_time', '600');
        $fileInfo = $request->request->get('file_info');
        $name = $fileInfo['name'] ?? null;
        $uploadedFile = $request->files->get('file');

        $token = $request->request->get('token');
        if (!$token) {
            throw new BadRequestHttpException('Missing token');
        }
        $JWTTokenManager->validateToken($assetId, $token);

        if (empty($name)) {
            throw new BadRequestHttpException('Missing name');
        }

        if (!$uploadedFile instanceof UploadedFile) {
            throw new BadRequestHttpException('Missing file');
        }
        if (!$uploadedFile->isValid()) {
            throw new BadRequestHttpException('Invalid uploaded file');
        }
        if (0 === $uploadedFile->getSize()) {
            throw new BadRequestHttpException('Empty file');
        }

        $asset = $em->getRepository(Asset::class)
            ->find($assetId);

        if (!$asset instanceof Asset) {
            throw new NotFoundHttpException(sprintf('Asset "%s" not found', $assetId));
        }

        $definition = $renditionManager->getDefinitionFromName($asset->getWorkspace(), $fileInfo['name']);
        if (!$definition) {
            throw new BadRequestHttpException(sprintf('Undefined rendition definition "%s"', $fileInfo['name']));
        }

        $extension = pathinfo($uploadedFile->getClientOriginalName(), PATHINFO_EXTENSION);
        $path = sprintf('%s/%s', $asset->getWorkspace()->getId(), $pathGenerator->generatePath($extension));

        $stream = fopen($uploadedFile->getRealPath(), 'r+');
        $storageManager->storeStream($path, $stream);
        fclose($stream);

        $renditionManager->createOrReplaceRendition(
            $asset,
            $definition,
            File::STORAGE_S3_MAIN,
            $path,
            $uploadedFile->getMimeType(),
            $uploadedFile->getSize()
        );

        $em->flush();

        return new Response();
    }
}
