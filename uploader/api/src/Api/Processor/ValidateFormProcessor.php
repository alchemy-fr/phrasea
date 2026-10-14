<?php

declare(strict_types=1);

namespace App\Api\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Form\FormValidator;
use App\Model\FormData;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

final readonly class ValidateFormProcessor implements ProcessorInterface
{
    public function __construct(private FormValidator $formValidator)
    {
    }

    /**
     * @param FormData $data
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): JsonResponse
    {
        /** @var Request $request */
        $request = $context['request'];

        $errors = $this->formValidator->validateForm($data->getData(), $data->getTarget(), $request);

        return new JsonResponse(['errors' => $errors]);
    }
}
