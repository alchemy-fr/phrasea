<?php

declare(strict_types=1);

namespace App\Controller;

use App\Form\FormSchemaManager;
use App\Model\FormSchema;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;

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

    public function __invoke(FormSchema $data)
    {
        $this->schemaLoader->persistSchema(null, $data->getSchema());

        return new JsonResponse(true);
    }
}
