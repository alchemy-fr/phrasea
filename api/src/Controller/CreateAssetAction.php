<?php

declare(strict_types=1);

namespace App\Controller;

use ApiPlatform\Core\Metadata\Resource\Factory\ResourceMetadataFactoryInterface;
use ApiPlatform\Core\Util\RequestAttributesExtractor;
use ApiPlatform\Core\Validator\ValidatorInterface;
use App\Model\Asset;
use League\Flysystem\FilesystemInterface;
use Ramsey\Uuid\Uuid;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

final class CreateAssetAction
{
    private $validator;
    private $resourceMetadataFactory;

    /**
     * @var FilesystemInterface
     */
    private $filesystem;

    public function __construct(
        ValidatorInterface $validator,
        ResourceMetadataFactoryInterface $resourceMetadataFactory,
        FilesystemInterface $filesystem
    ) {
        $this->validator = $validator;
        $this->resourceMetadataFactory = $resourceMetadataFactory;
        $this->filesystem = $filesystem;
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
        $uuid = Uuid::uuid4()->toString();
        $path = sprintf(
            '%s/%s/%s-%s',
            substr($uuid, 0, 2),
            substr($uuid, 2, 2),
            $uuid,
            $extension
        );
        $asset->setPath($path);

        $asset->setOriginalName($uploadedFile->getClientOriginalName());
        $asset->setSize($uploadedFile->getSize());

        $this->validate($asset, $request);

        $stream = fopen($uploadedFile->getRealPath(), 'r+');
        $this->filesystem->writeStream($asset->getPath(), $stream);
        fclose($stream);

        return $asset;
    }

    private function validate(Asset $asset, Request $request): void
    {
        $attributes = RequestAttributesExtractor::extractAttributes($request);
        $resourceMetadata = $this->resourceMetadataFactory->create(Asset::class);
        $validationGroups = $resourceMetadata->getOperationAttribute(
            $attributes,
            'validation_groups',
            null,
            true
        );

        $this->validator->validate($asset, ['groups' => $validationGroups]);
    }
}
