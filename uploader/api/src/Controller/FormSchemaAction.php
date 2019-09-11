<?php

declare(strict_types=1);

namespace App\Controller;

use App\Form\FormSchemaManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;

final class FormSchemaAction extends AbstractController
{
    /**
     * @var FormSchemaManager
     */
    private $schemaLoader;

    public function __construct(FormSchemaManager $schemaLoader)
    {
        $this->schemaLoader = $schemaLoader;
    }

    public function __invoke()
    {
        return new JsonResponse($this->schemaLoader->loadSchema(null));
    }
}
