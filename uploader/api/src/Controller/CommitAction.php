<?php

declare(strict_types=1);

namespace App\Controller;

use Alchemy\RemoteAuthBundle\Model\RemoteUser;
use Alchemy\ReportBundle\ReportUserService;
use App\Consumer\Handler\CommitHandler;
use App\Entity\Commit;
use App\Form\FormValidator;
use App\Report\UploaderLogActionInterface;
use App\Storage\AssetManager;
use App\Validation\CommitValidator;
use Arthem\Bundle\RabbitBundle\Consumer\Event\EventMessage;
use Arthem\Bundle\RabbitBundle\Producer\EventProducer;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

final class CommitAction extends AbstractController
{
    private AssetManager $assetManager;
    private EventProducer $eventProducer;
    private FormValidator $formValidator;
    private CommitValidator $commitValidator;
    private ReportUserService $reportClient;

    public function __construct(
        AssetManager $assetManager,
        EventProducer $eventProducer,
        FormValidator $formValidator,
        CommitValidator $commitValidator,
        ReportUserService $reportClient
    ) {
        $this->assetManager = $assetManager;
        $this->eventProducer = $eventProducer;
        $this->formValidator = $formValidator;
        $this->commitValidator = $commitValidator;
        $this->reportClient = $reportClient;
    }

    public function __invoke(Commit $data, Request $request)
    {
        $errors = $this->formValidator->validateForm($data->getFormData(), $data->getTarget(), $request);
        if (!empty($errors)) {
            throw new BadRequestHttpException(sprintf('Form errors: %s', json_encode($errors)));
        }

        $totalSize = $this->assetManager->getTotalSize($data->getFiles());
        $data->setTotalSize($totalSize);

        $this->commitValidator->validate($data);

        /** @var RemoteUser $user */
        $user = $this->getUser();
        $data->setUserId($user->getId());
        $data->setLocale($request->getLocale() ?? $request->getDefaultLocale());

        $formData = $data->getFormData();
        $notifyEmailField = '__notify_email';
        if (isset($formData[$notifyEmailField]) && true === $formData[$notifyEmailField]) {
            $data->setNotifyEmail($user->getEmail());
        }
        $data->setFormData(FormValidator::cleanExtraFields($formData));

        $this->eventProducer->publish(new EventMessage(CommitHandler::EVENT, $data->toArray()));

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
