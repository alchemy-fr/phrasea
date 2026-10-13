<?php

declare(strict_types=1);

namespace App\Api\Processor;

use Alchemy\AuthBundle\Security\JwtUser;
use Alchemy\ReportBundle\ReportUserService;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Commit;
use App\Entity\Target;
use App\Form\FormValidator;
use App\Report\UploaderLogActionInterface;
use App\Storage\AssetManager;
use App\Validation\CommitValidator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
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

        $errors = $this->formValidator->validateForm($data->getFormData(), $data->getTarget(), $request);
        if (!empty($errors)) {
            throw new BadRequestHttpException(sprintf('Form errors: %s', json_encode($errors, JSON_THROW_ON_ERROR)));
        }

        $totalSize = $this->assetManager->getTotalSize($data->getFiles());
        $data->setTotalSize($totalSize);

        $this->commitValidator->validate($data);

        /** @var JwtUser $user */
        $user = $this->security->getUser();
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
}
