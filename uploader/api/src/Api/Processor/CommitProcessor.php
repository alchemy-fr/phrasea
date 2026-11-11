<?php

declare(strict_types=1);

namespace App\Api\Processor;

use Alchemy\AuthBundle\Security\JwtUser;
use Alchemy\ReportBundle\ReportUserService;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Asset;
use App\Entity\Commit;
use App\Entity\Target;
use App\Form\FormValidator;
use App\Report\UploaderLogActionInterface;
use App\Security\Voter\TargetVoter;
use App\Storage\AssetManager;
use App\Validation\CommitValidator;
use Doctrine\ORM\EntityManagerInterface;
use Ramsey\Uuid\Uuid;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * The commit is not persisted here: it is dispatched as a message and stored by its handler.
 */
final readonly class CommitProcessor implements ProcessorInterface
{
    public function __construct(
        private AssetManager $assetManager,
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

        if (!empty($targetSlug = $request->request->get('targetSlug'))) {
            $target = $this->em->getRepository(Target::class)->findOneBy([
                'slug' => $targetSlug,
            ]);
            if (!$target instanceof Target) {
                throw new BadRequestHttpException(sprintf('Target "%s" does not exist', $targetSlug));
            }
            $data->setTarget($target);
        }

        if (!$this->security->isGranted(TargetVoter::UPLOAD, $data->getTarget())) {
            throw new AccessDeniedHttpException('Cannot upload to this target');
        }

        $data->setFormData($this->formValidator->prepareData($data->getFormData(), $data->getTarget()));
        $errors = $this->formValidator->validateForm($data->getFormData(), $data->getTarget());
        if (!empty($errors)) {
            throw new BadRequestHttpException(sprintf('Form errors: %s', json_encode($errors, JSON_THROW_ON_ERROR)));
        }

        $user = $this->security->getUser();
        if (!$user instanceof JwtUser) {
            throw new AccessDeniedHttpException('Authentication required');
        }

        $this->assertFilesCanBeCommitted($data, $user);

        $totalSize = $this->assetManager->getTotalSize($data->getFiles());
        $data->setTotalSize($totalSize);

        $this->commitValidator->validate($data);

        $data->setUserId($user->getId());
        $data->setLocale($request->getLocale() ?? $request->getDefaultLocale());

        $formData = $data->getFormData();
        if ($formData['__notify'] ?? false) {
            $data->setNotify(true);
        }
        $data->setFormData(FormValidator::cleanExtraFields($formData));

        $this->bus->dispatch($data->toMessage());

        $this->reportClient->pushHttpRequestLog(
            $request,
            UploaderLogActionInterface::UPLOAD_COMMIT,
            $data->getId(),
            [
                'userId' => $user->getId(),
                'username' => $user->getUsername(),
                'totalSize' => $totalSize,
                'fileCount' => count($data->getFiles()),
            ]
        );

        return new JsonResponse(true);
    }

    private function assertFilesCanBeCommitted(Commit $commit, JwtUser $user): void
    {
        $files = array_values(array_unique($commit->getFiles()));
        if (empty($files) || array_any($files, fn ($id): bool => !is_string($id) || !Uuid::isValid($id))) {
            throw new BadRequestHttpException('Invalid files');
        }
        $commit->setFiles($files);

        $assets = $this->em->getRepository(Asset::class)->findBy(['id' => $files]);
        if (count($assets) !== count($files)) {
            throw new BadRequestHttpException('Some files do not exist');
        }

        foreach ($assets as $asset) {
            if ($asset->getUserId() !== $user->getId()
                || $asset->getTarget()?->getId() !== $commit->getTarget()->getId()
                || $asset->isCommitted()) {
                throw new AccessDeniedHttpException(sprintf('File "%s" cannot be committed', $asset->getId()));
            }
        }
    }
}
