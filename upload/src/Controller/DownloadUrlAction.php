<?php

declare(strict_types=1);

namespace App\Controller;

use ApiPlatform\Core\Metadata\Resource\Factory\ResourceMetadataFactoryInterface;
use ApiPlatform\Core\Validator\ValidatorInterface;
use App\Consumer\Handler\CommitHandler;
use App\Consumer\Handler\DownloadHandler;
use App\Form\FormValidator;
use App\Model\DownloadUrl;
use App\Model\FormData;
use App\Model\User;
use Arthem\Bundle\RabbitBundle\Consumer\Event\EventMessage;
use Arthem\Bundle\RabbitBundle\Producer\EventProducer;
use OldSound\RabbitMqBundle\RabbitMq\ProducerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class DownloadUrlAction extends AbstractController
{
    private $validator;
    private $resourceMetadataFactory;

    /**
     * @var EventProducer
     */
    private $eventProducer;

    /**
     * @var FormValidator
     */
    private $formValidator;

    public function __construct(
        ValidatorInterface $validator,
        ResourceMetadataFactoryInterface $resourceMetadataFactory,
        EventProducer $eventProducer,
        FormValidator $formValidator
    ) {
        $this->validator = $validator;
        $this->resourceMetadataFactory = $resourceMetadataFactory;
        $this->eventProducer = $eventProducer;
        $this->formValidator = $formValidator;
    }

    public function __invoke(DownloadUrl $data, Request $request, ValidateFormAction $validateFormAction): Response
    {
        $errors = $this->formValidator->validateForm($data->getData(), $request);
        if (!empty($errors)) {
            return new JsonResponse(['errors' => $errors]);
        }

        /** @var User $user */
        $user = $this->getUser();

        $this->eventProducer->publish(new EventMessage(DownloadHandler::EVENT, [
            'url' => $data->getUrl(),
            'form_data' => $data->getData(),
            'user_id' => $user->getId(),
        ]));

        return new JsonResponse(true);
    }
}
