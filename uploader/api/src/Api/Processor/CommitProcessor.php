<?php

declare(strict_types=1);

namespace App\Api\Processor;

use Alchemy\AuthBundle\Security\JwtUser;
use Alchemy\ReportBundle\ReportUserService;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Consumer\Handler\AssetConsumerNotify;
use App\Entity\Asset;
use App\Entity\AssetRepository;
use App\Entity\Commit;
use App\Entity\Target;
use App\Entity\UploadLink;
use App\Form\FormValidator;
use App\Report\UploaderLogActionInterface;
use App\Security\UploadLinkUser;
use App\Security\Voter\TargetVoter;
use App\Storage\AssetManager;
use App\Storage\CommitManager;
use App\Validation\CommitValidator;
use Doctrine\ORM\EntityManagerInterface;
use Ramsey\Uuid\Uuid;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * The commit is stored with its files right away: committing files that another commit took meanwhile is a
 * conflict (409), not an empty commit.
 *
 * Committing again files already stored as one commit, all of them and them only, by the same owner to the same
 * target is a retry of that commit (the first answer was lost or failed after the storage): it answers 200 without
 * storing, notifying or logging anything again, since the first request may have notified the target already.
 */
final readonly class CommitProcessor implements ProcessorInterface
{
    public function __construct(
        private AssetManager $assetManager,
        private CommitManager $commitManager,
        private MessageBusInterface $bus,
        private FormValidator $formValidator,
        private CommitValidator $commitValidator,
        private ReportUserService $reportClient,
        private EntityManagerInterface $em,
        private Security $security,
    ) {
    }

    /**
     * @param Commit $data
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): JsonResponse
    {
        /** @var Request $request */
        $request = $context['request'];

        $user = $this->security->getUser();
        $link = null;
        if ($user instanceof UploadLinkUser) {
            $link = $this->em->find(UploadLink::class, $user->getLinkId())
                ?? throw new AccessDeniedHttpException('Upload link not found');
        }

        if (!empty($targetSlug = $request->request->get('targetSlug'))) {
            $target = $this->em->getRepository(Target::class)->findOneBy([
                'slug' => $targetSlug,
            ]);
            if (!$target instanceof Target) {
                throw new BadRequestHttpException(sprintf('Target "%s" does not exist', $targetSlug));
            }
            $data->setTarget($target);
        }

        if (null !== $link) {
            if (null === $data->getTarget()) {
                $data->setTarget($link->getTarget());
            } elseif ($data->getTarget()->getId() !== $link->getTarget()->getId()) {
                throw new AccessDeniedHttpException('Cannot upload to this target');
            }
        }

        if (!$this->security->isGranted(TargetVoter::UPLOAD, $data->getTarget())) {
            throw new AccessDeniedHttpException('Cannot upload to this target');
        }

        $data->setFormData($this->formValidator->prepareData($data->getFormData(), $data->getTarget()));
        $errors = $this->formValidator->validateForm($data->getFormData(), $data->getTarget());
        if (!empty($errors)) {
            throw new BadRequestHttpException(sprintf('Form errors: %s', json_encode($errors, JSON_THROW_ON_ERROR)));
        }

        if (null !== $link) {
            $userId = (string) $link->getOwnerId();
            $username = 'upload-link:'.($link->getName() ?? $link->getId());
        } elseif ($user instanceof JwtUser) {
            $userId = $user->getId();
            $username = $user->getUsername();
        } else {
            throw new AccessDeniedHttpException('Authentication required');
        }

        if (null !== $this->assertFilesCanBeCommitted($data, $userId, $link)) {
            return new JsonResponse(true);
        }

        $totalSize = $this->assetManager->getTotalSize($data->getFiles());
        $data->setTotalSize($totalSize);

        $this->commitValidator->validate($data);

        $data->setUserId($userId);
        $data->setLocale($request->getLocale() ?? $request->getDefaultLocale());

        $formData = $data->getFormData();
        // Nobody to notify behind a link: the commit is attributed to its creator
        if (null === $link && ($formData['__notify'] ?? false)) {
            $data->setNotify(true);
        }
        $data->setFormData(FormValidator::cleanExtraFields($formData));

        if (null !== $link) {
            $data->setNotify(false);
            $data->setUploadLink($link);
            // Flushed with the commit
            $link->setLastCommitAt(new \DateTimeImmutable());
        }

        // Rebuilt from what was checked only
        $commit = Commit::fromMessage($data->toMessage(), $this->em);
        if (!$this->commitManager->store($commit)) {
            // The same commit sent twice at once (a double click)
            $commitIds = array_unique($this->getAssetRepository()->getCommitIdsOfAssets($data->getFiles()));
            $existingId = 1 === count($commitIds) ? reset($commitIds) : null;
            if (null !== $existingId
                && $this->isStoredIdentically($this->em->find(Commit::class, $existingId), $data->getFiles(), $data->getTarget(), $userId, $link)) {
                return new JsonResponse(true);
            }

            throw new ConflictHttpException('These files have been committed meanwhile');
        }

        $this->bus->dispatch(new AssetConsumerNotify($commit->getId()));

        $this->reportClient->pushHttpRequestLog(
            $request,
            UploaderLogActionInterface::UPLOAD_COMMIT,
            $commit->getId(),
            [
                'userId' => $userId,
                'username' => $username,
                'totalSize' => $totalSize,
                'fileCount' => count($data->getFiles()),
            ]
        );

        return new JsonResponse(true);
    }

    /**
     * The files of a link are committed through it only, the ones of a user by them only.
     *
     * @return Commit|null the commit already stored identically (a retry), null when the files are not committed
     */
    private function assertFilesCanBeCommitted(Commit $commit, string $userId, ?UploadLink $link): ?Commit
    {
        if (count($commit->getFiles()) > Commit::MAX_FILES) {
            throw new BadRequestHttpException(sprintf('Too many files (%d > %d)', count($commit->getFiles()), Commit::MAX_FILES));
        }

        $files = array_values(array_unique($commit->getFiles()));
        if (empty($files) || array_any($files, fn ($id): bool => !is_string($id) || !Uuid::isValid($id))) {
            throw new BadRequestHttpException('Invalid files');
        }
        $commit->setFiles($files);

        $assets = $this->getAssetRepository()->findBy(['id' => $files]);
        if (count($assets) !== count($files)) {
            throw new BadRequestHttpException('Some files do not exist');
        }

        $committed = null;
        $commitIds = [];
        foreach ($assets as $asset) {
            $owned = null !== $link
                ? $asset->getUploadLink()?->getId() === $link->getId()
                : null === $asset->getUploadLink() && $asset->getUserId() === $userId;
            if (!$owned || $asset->getTarget()?->getId() !== $commit->getTarget()->getId()) {
                throw new AccessDeniedHttpException(sprintf('File "%s" cannot be committed', $asset->getId()));
            }

            $commitIds[$asset->getCommit()?->getId() ?? ''] = true;
            if ($asset->isCommitted()) {
                $committed ??= $asset;
            }
        }

        if (null === $committed) {
            return null;
        }

        $existing = $committed->getCommit();
        if (1 === count($commitIds) && $this->isStoredIdentically($existing, $files, $commit->getTarget(), $userId, $link)) {
            return $existing;
        }

        throw new AccessDeniedHttpException(sprintf('File "%s" cannot be committed', $committed->getId()));
    }

    /**
     * Whether the commit holds these files and them only, from the same owner to the same target.
     *
     * @param string[] $files
     */
    private function isStoredIdentically(?Commit $existing, array $files, Target $target, string $userId, ?UploadLink $link): bool
    {
        if (null === $existing || $existing->getTarget()?->getId() !== $target->getId()) {
            return false;
        }

        $sameOwner = null !== $link
            ? $existing->getUploadLink()?->getId() === $link->getId()
            : null === $existing->getUploadLink() && $existing->getUserId() === $userId;
        if (!$sameOwner) {
            return false;
        }

        $stored = array_map(strtolower(...), $this->getAssetRepository()->getCommitAssetIds($existing->getId()));
        $requested = array_map(strtolower(...), $files);
        sort($stored);
        sort($requested);

        return $stored === $requested;
    }

    private function getAssetRepository(): AssetRepository
    {
        /** @var AssetRepository $repository */
        $repository = $this->em->getRepository(Asset::class);

        return $repository;
    }
}
