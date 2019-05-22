<?php

declare(strict_types=1);

namespace App\Controller;

use ApiPlatform\Core\Metadata\Resource\Factory\ResourceMetadataFactoryInterface;
use ApiPlatform\Core\Validator\ValidatorInterface;
use App\Entity\Asset;
use App\Entity\FormSchema;
use App\Form\FormSchemaManager;
use App\Form\LiFormToFormTransformer;
use App\Model\Commit;
use App\Model\Form;
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

final class FormEditSchemaAction extends AbstractController
{
    /**
     * @var FormSchemaManager
     */
    private $schemaLoader;

    public function __construct(FormSchemaManager $schemaLoader)
    {
        $this->schemaLoader = $schemaLoader;
    }

    public function __invoke(Form $data)
    {
        $this->schemaLoader->persistSchema(null, $data->getData());

        return new JsonResponse(true);
    }
}
