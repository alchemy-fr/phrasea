<?php

declare(strict_types=1);

namespace App\Controller;

use Alchemy\AuthBundle\Security\JwtUser;
use Alchemy\ReportBundle\ReportUserService;
use App\Entity\Commit;
use App\Entity\Target;
use App\Form\FormValidator;
use App\Report\UploaderLogActionInterface;
use App\Storage\AssetManager;
use App\Validation\CommitValidator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Messenger\MessageBusInterface;

final class CommitAction extends AbstractController
{
    public function __construct(
        private readonly AssetManager $assetManager,
        private readonly MessageBusInterface $bus,
        private readonly FormValidator $formValidator,
        private readonly CommitValidator $commitValidator,
        private readonly ReportUserService $reportClient,
        private readonly EntityManagerInterface $em,
    ) {
    }

    public function __invoke(Commit $data, Request $request): JsonResponse
    {
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
        $user = $this->getUser();
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
