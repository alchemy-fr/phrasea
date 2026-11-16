<?php

declare(strict_types=1);

namespace App\Api\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Form\FormValidator;
use App\Model\FormData;
use App\Security\Voter\TargetVoter;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * The errors tell the fields and constraints of the form: only for whoever may read the target, as its form.
 */
final readonly class ValidateFormProcessor implements ProcessorInterface
{
    public function __construct(
        private FormValidator $formValidator,
        private Security $security,
    ) {
    }

    /**
     * @param FormData $data
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): JsonResponse
    {
        if (!$this->security->isGranted(TargetVoter::READ, $data->getTarget())) {
            throw new AccessDeniedHttpException('Cannot read this target');
        }

        $errors = $this->formValidator->validateForm($data->getData(), $data->getTarget());

        return new JsonResponse(['errors' => $errors]);
    }
}
