<?php

declare(strict_types=1);

namespace App\Controller;

use ApiPlatform\Core\Metadata\Resource\Factory\ResourceMetadataFactoryInterface;
use ApiPlatform\Core\Validator\ValidatorInterface;
use App\Entity\Asset;
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

final class ValidateFormAction extends AbstractController
{
    /**
     * @var LiFormToFormTransformer
     */
    private $formGenerator;
    /**
     * @var FormSchemaManager
     */
    private $schemaLoader;

    public function __construct(
        LiFormToFormTransformer $formGenerator,
        FormSchemaManager $schemaLoader
    )
    {
        $this->formGenerator = $formGenerator;
        $this->schemaLoader = $schemaLoader;
    }

    public function __invoke(Form $data)
    {
        $formData = $data->getData();

        $schema = $this->schemaLoader->loadSchema();
        $form = $this->formGenerator->createFormFromConfig($schema);

        $form->submit($formData);
        if ($form->isSubmitted() && $form->isValid()) {
            return new JsonResponse(['errors' => []]);
        }


        return new JsonResponse(['errors' => $this->getFormErrors($form)]);
    }

    protected function getFormErrors(FormInterface $form): array
    {
        $errors = [];

        // Global
        foreach ($form->getErrors() as $error) {
            $errors['_form'][] = $error->getMessage();
        }

        // Fields
        foreach ($form as $child/** @var FormInterface $child */) {
            if (!$child->isValid()) {
                foreach ($child->getErrors() as $error) {
                    $errors[$child->getName()][] = $error->getMessage();
                }
            }
        }

        return $errors;
    }
}
