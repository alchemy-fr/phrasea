<?php

declare(strict_types=1);

namespace App\Api\Processor;

use Alchemy\AuthBundle\Security\JwtUser;
use Alchemy\StorageBundle\Api\Dto\MultipartUploadInput;
use Alchemy\StorageBundle\Entity\MultipartUpload;
use Alchemy\StorageBundle\Storage\FileStorageManager;
use Alchemy\StorageBundle\Storage\PathGeneratorInterface;
use Alchemy\StorageBundle\Upload\FileValidator;
use Alchemy\StorageBundle\Upload\UploadManager;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Asset;
use App\Entity\Target;
use App\Entity\UploadLink;
use App\Exception\UploadLinkQuotaExceededException;
use App\Repository\TargetRepository;
use App\Repository\UploadLinkRepository;
use App\Security\UploadLinkUser;
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
        private UploadLinkRepository $uploadLinkRepository,
        private Security $security,
        private FileValidator $fileValidator,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): Asset
    {
        /** @var Request $request */
        $request = $context['request'];

        $user = $this->security->getUser();
        $link = null;
        if ($user instanceof UploadLinkUser) {
            $link = $this->uploadLinkRepository->find($user->getLinkId())
                ?? throw new AccessDeniedHttpException('Upload link not found');
            $target = $this->resolveLinkTarget($request, $link);
            $userId = (string) $link->getOwnerId();
        } else {
            $target = $this->resolveTarget($request);
            if (!$user instanceof JwtUser) {
                throw new AccessDeniedHttpException('Authentication required');
            }
            $userId = $user->getId();
        }

        if (!$this->security->isGranted(TargetVoter::UPLOAD, $target)) {
            throw new AccessDeniedHttpException('Cannot upload to this target');
        }

        if ($request->request->all('multipart')) {
            return $this->handleMultipartUpload($request, $target, $user->getUserIdentifier(), $userId, $link);
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
        $size = (int) $uploadedFile->getSize();
        if (null !== $link) {
            $this->assertFits($link, $size);
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

        if (null !== $link) {
            $this->reserve($link, $size, $path);
        }

        return $this->assetManager->createAsset(
            $target,
            $path,
            $uploadedFile->getMimeType(),
            $uploadedFile->getClientOriginalName(),
            $size,
            $userId,
            $request->request->all('data'),
            $link,
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

    /**
     * The target of the link: naming another one is denied.
     */
    private function resolveLinkTarget(Request $request, UploadLink $link): Target
    {
        $target = $link->getTarget();
        if (empty($request->request->get('targetSlug')) && empty($request->request->get('targetId'))) {
            return $target;
        }

        if ($this->resolveTarget($request)->getId() !== $target->getId()) {
            throw new AccessDeniedHttpException('Cannot upload to this target');
        }

        return $target;
    }

    private function handleMultipartUpload(Request $request, Target $target, string $ownerIdentifier, string $userId, ?UploadLink $link): Asset
    {
        $input = MultipartUploadInput::fromRequest($request);

        if (null !== $link) {
            // Before completing it: the declared size is checked against the real one when completing
            $declared = $this->em->find(MultipartUpload::class, $input->uploadId);
            if ($declared instanceof MultipartUpload && $declared->isOwnedBy($ownerIdentifier)) {
                $this->assertFits($link, $declared->getSize());
            }
        }

        $multipartUpload = $this->uploadManager->handleMultipartUpload($input, $ownerIdentifier);

        if (null !== $link) {
            try {
                $this->reserve($link, $multipartUpload->getSize(), $multipartUpload->getPath());
            } catch (UploadLinkQuotaExceededException $e) {
                $this->em->remove($multipartUpload);
                $this->em->flush();

                throw $e;
            }
        }

        return $this->assetManager->createAsset(
            $target,
            $multipartUpload->getPath(),
            $multipartUpload->getType(),
            $multipartUpload->getFilename(),
            $multipartUpload->getSize(),
            $userId,
            $request->request->all('data'),
            $link,
        );
    }

    /**
     * Early check, before anything is stored: the reservation is what enforces the limits.
     */
    private function assertFits(UploadLink $link, int $size): void
    {
        if (!$link->isUsable(new \DateTimeImmutable())
            || (null !== $link->getMaxAssets() && $link->getUsedAssets() + 1 > $link->getMaxAssets())
            || (null !== $link->getMaxTotalSize() && $link->getUsedSize() + $size > $link->getMaxTotalSize())) {
            throw new UploadLinkQuotaExceededException();
        }
    }

    /**
     * Counts the stored file against the limits of the link, or removes it.
     */
    private function reserve(UploadLink $link, int $size, string $path): void
    {
        if ($this->uploadLinkRepository->reserve($link->getId(), $size, new \DateTimeImmutable())) {
            // The managed link must not write its former counters back on the next flush
            $this->em->refresh($link);

            return;
        }

        $this->storageManager->delete($path);

        throw new UploadLinkQuotaExceededException();
    }
}
