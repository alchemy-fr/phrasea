<?php

declare(strict_types=1);

namespace App\Controller;

use ApiPlatform\Core\Metadata\Resource\Factory\ResourceMetadataFactoryInterface;
use ApiPlatform\Core\Validator\ValidatorInterface;
use App\Entity\Asset;
use App\Storage\FileStorageManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

final class CreateAssetAction extends AbstractController
{
    private $validator;
    private $resourceMetadataFactory;

    /**
     * @var FileStorageManager
     */
    private $storageManager;
    /**
     * @var EntityManagerInterface
     */
    private $em;

    public function __construct(
        ValidatorInterface $validator,
        ResourceMetadataFactoryInterface $resourceMetadataFactory,
        FileStorageManager $storageManager,
        EntityManagerInterface $em
    ) {
        $this->validator = $validator;
        $this->resourceMetadataFactory = $resourceMetadataFactory;
        $this->storageManager = $storageManager;
        $this->em = $em;
    }

    public function __invoke(Request $request): Asset
    {
        /** @var UploadedFile $uploadedFile */
        $uploadedFile = $request->files->get('file');

        if (!$uploadedFile) {
            throw new BadRequestHttpException('"file" is required');
        }
        if (!$uploadedFile->isValid()) {
            throw new BadRequestHttpException('Invalid uploaded file');
        }
        if (0 === $uploadedFile->getSize()) {
            throw new BadRequestHttpException('Empty file');
        }

        $asset = new Asset();
        $asset->setFile($uploadedFile);

        $extension = pathinfo($uploadedFile->getClientOriginalName(), PATHINFO_EXTENSION);
        $path = $this->storageManager->generatePath($extension);
        $asset->setPath($path);
        $asset->setMimeType($uploadedFile->getMimeType());
        $asset->setOriginalName($uploadedFile->getClientOriginalName());
        $asset->setSize($uploadedFile->getSize());

        $stream = fopen($uploadedFile->getRealPath(), 'r+');
        $this->storageManager->storeStream($path, $stream);
        fclose($stream);

        $this->em->persist($asset);
        $this->em->flush();

        return $asset;
    }
}
