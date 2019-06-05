<?php

declare(strict_types=1);

namespace App\Controller;

use ApiPlatform\Core\Metadata\Resource\Factory\ResourceMetadataFactoryInterface;
use ApiPlatform\Core\Validator\ValidatorInterface;
use App\Model\DownloadUrl;
use App\Model\User;
use OldSound\RabbitMqBundle\RabbitMq\ProducerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

final class DownloadUrlAction extends AbstractController
{
    private $validator;
    private $resourceMetadataFactory;

    /**
     * @var ProducerInterface
     */
    private $downloadProducer;

    public function __construct(
        ValidatorInterface $validator,
        ResourceMetadataFactoryInterface $resourceMetadataFactory,
        ProducerInterface $downloadProducer
    ) {
        $this->validator = $validator;
        $this->resourceMetadataFactory = $resourceMetadataFactory;
        $this->downloadProducer = $downloadProducer;
    }

    public function __invoke(DownloadUrl $data): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $message = json_encode([
            'url' => $data->getUrl(),
            'form_data' => $data->getFormData(),
            'user_id' => $user->getId(),
        ]);
        $this->downloadProducer->publish($message);

        return new JsonResponse(true);
    }
}
