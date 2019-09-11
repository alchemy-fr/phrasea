<?php

declare(strict_types=1);

namespace App\Controller;

use App\Form\FormSchemaManager;
use App\Model\FormSchema;
use App\Security\Voter\FormDataEditorVoter;
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
        $this->denyAccessUnlessGranted(FormDataEditorVoter::EDIT_FORM_SCHEMA);

        $this->schemaLoader->persistSchema(null, $data->getSchema());

        return new JsonResponse(true);
    }
}
