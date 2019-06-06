<?php

declare(strict_types=1);

namespace App\Controller;

use ApiPlatform\Core\Metadata\Resource\Factory\ResourceMetadataFactoryInterface;
use ApiPlatform\Core\Validator\ValidatorInterface;
use App\Form\FormValidator;
use App\Model\DownloadUrl;
use App\Model\FormData;
use App\Model\User;
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
     * @var ProducerInterface
     */
    private $downloadProducer;
    /**
     * @var FormValidator
     */
    private $formValidator;

    public function __construct(
        ValidatorInterface $validator,
        ResourceMetadataFactoryInterface $resourceMetadataFactory,
        ProducerInterface $downloadProducer,
        FormValidator $formValidator
    ) {
        $this->validator = $validator;
        $this->resourceMetadataFactory = $resourceMetadataFactory;
        $this->downloadProducer = $downloadProducer;
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

        $message = json_encode([
            'url' => $data->getUrl(),
            'form_data' => $data->getData(),
            'user_id' => $user->getId(),
        ]);
        $this->downloadProducer->publish($message);

        return new JsonResponse(true);
    }
}
