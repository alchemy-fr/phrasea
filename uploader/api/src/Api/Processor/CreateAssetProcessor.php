<?php

declare(strict_types=1);

namespace App\Api\Processor;

use Alchemy\AuthBundle\Security\JwtUser;
use Alchemy\StorageBundle\Api\Dto\MultipartUploadInput;
use Alchemy\StorageBundle\Storage\FileStorageManager;
use Alchemy\StorageBundle\Storage\PathGeneratorInterface;
use Alchemy\StorageBundle\Upload\UploadManager;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Asset;
use App\Entity\Target;
use App\Repository\TargetRepository;
use App\Security\Voter\TargetVoter;
use App\Storage\AssetManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

final readonly class CreateAssetProcessor implements ProcessorInterface
{
    public function __construct(
        private FileStorageManager $storageManager,
        private AssetManager $assetManager,
        private UploadManager $uploadManager,
        private PathGeneratorInterface $pathGenerator,
        private EntityManagerInterface $em,
        private TargetRepository $targetRepository,
        private Security $security,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): Asset
    {
        /** @var Request $request */
        $request = $context['request'];

        $target = $this->resolveTarget($request);

        if ($request->request->all('multipart')) {
            return $this->handleMultipartUpload($request, $target);
        }

        if (!$this->security->isGranted(TargetVoter::UPLOAD, $target)) {
            throw new AccessDeniedHttpException('Cannot upload to this target');
        }

        ini_set('max_execution_time', '600');

        /** @var UploadedFile|null $uploadedFile */
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

        $extension = pathinfo($uploadedFile->getClientOriginalName(), PATHINFO_EXTENSION);
        $path = $this->pathGenerator->generatePath($extension);

        $stream = fopen($uploadedFile->getRealPath(), 'r+');
        $this->storageManager->storeStream($path, $stream);
        fclose($stream);

        return $this->assetManager->createAsset(
            $target,
            $path,
            $uploadedFile->getMimeType(),
            $uploadedFile->getClientOriginalName(),
            $uploadedFile->getSize(),
            $this->getUser()->getId(),
            $request->request->all('data')
        );
    }

    private function resolveTarget(Request $request): Target
    {
        if (!empty($targetSlug = $request->request->get('targetSlug'))) {
            $target = $this->targetRepository->findOneBy([
                'slug' => $targetSlug,
            ]);

            if (!$target instanceof Target) {
                throw new BadRequestHttpException(sprintf('Target "%s" does not exist', $targetSlug));
            }

            return $target;
        }

        if (!empty($targetId = $request->request->get('targetId'))) {
            $target = $this->em->find(Target::class, $targetId);

            if (!$target instanceof Target) {
                throw new BadRequestHttpException(sprintf('Target "%s" does not exist', $targetId));
            }

            return $target;
        }

        throw new BadRequestHttpException('"targetId" or "targetSlug" is required');
    }

    private function handleMultipartUpload(Request $request, Target $target): Asset
    {
        $multipartUpload = $this->uploadManager->handleMultipartUpload(MultipartUploadInput::fromRequest($request));

        return $this->assetManager->createAsset(
            $target,
            $multipartUpload->getPath(),
            $multipartUpload->getType(),
            $multipartUpload->getFilename(),
            $multipartUpload->getSize(),
            $this->getUser()->getId(),
            $request->request->all('data')
        );
    }

    private function getUser(): JwtUser
    {
        $user = $this->security->getUser();
        if (!$user instanceof JwtUser) {
            throw new AccessDeniedHttpException('Authentication required');
        }

        return $user;
    }
}
