<?php

declare(strict_types=1);

namespace App\Controller;

use App\Consumer\Handler\CommitHandler;
use App\Form\FormValidator;
use App\Entity\Commit;
use App\Model\User;
use App\Storage\AssetManager;
use Arthem\Bundle\RabbitBundle\Consumer\Event\EventMessage;
use Arthem\Bundle\RabbitBundle\Producer\EventProducer;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

final class CommitAction extends AbstractController
{
    /**
     * @var AssetManager
     */
    private $assetManager;

    /**
     * @var EventProducer
     */
    private $eventProducer;

    /**
     * @var FormValidator
     */
    private $formValidator;

    public function __construct(
        AssetManager $assetManager,
        EventProducer $eventProducer,
        FormValidator $formValidator
    ) {
        $this->assetManager = $assetManager;
        $this->eventProducer = $eventProducer;
        $this->formValidator = $formValidator;
    }

    public function __invoke(Commit $data, Request $request)
    {
        $errors = $this->formValidator->validateForm($data->getFormData(), $request);
        if (!empty($errors)) {
            throw new BadRequestHttpException(sprintf('Form errors: %s', json_encode($errors)));
        }

        /** @var User $user */
        $user = $this->getUser();
        $data->setUserId($user->getId());

        $this->eventProducer->publish(new EventMessage(CommitHandler::EVENT, $data->toArray()));

        return new JsonResponse(true);
    }
}
