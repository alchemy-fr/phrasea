<?php

declare(strict_types=1);

namespace App\Controller;

use ApiPlatform\Core\Metadata\Resource\Factory\ResourceMetadataFactoryInterface;
use ApiPlatform\Core\Validator\ValidatorInterface;
use App\Entity\Asset;
use App\Form\FormSchemaLoader;
use App\Form\LiFormToFormTransformer;
use App\Model\Commit;
use App\Model\User;
use App\Storage\AssetManager;
use App\Storage\FileStorageManager;
use OldSound\RabbitMqBundle\RabbitMq\ProducerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

final class FormSchemaAction extends AbstractController
{
    /**
     * @var FormSchemaLoader
     */
    private $schemaLoader;

    public function __construct(FormSchemaLoader $schemaLoader)
    {
        $this->schemaLoader = $schemaLoader;
    }

    public function __invoke()
    {
        return new JsonResponse($this->schemaLoader->loadSchema());
    }
}
