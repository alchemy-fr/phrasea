<?php

namespace App\Api\Processor;

use Alchemy\AuthBundle\Security\Traits\SecurityAwareTrait;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Api\DTO\Input\DownloadUrlInput;
use App\Consumer\Handler\Download;
use App\Entity\Target;
use App\Form\FormValidator;
use App\Repository\TargetRepository;
use App\Security\Voter\TargetVoter;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Messenger\MessageBusInterface;

final class DownloadUrlProcessor implements ProcessorInterface
{
    use SecurityAwareTrait;

    public function __construct(
        private readonly MessageBusInterface $bus,
        private readonly FormValidator $formValidator,
        private readonly TargetRepository $targetRepository,
        private readonly RequestStack $requestStack,
    ) {
    }

    /**
     * @param DownloadUrlInput $data
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): Response
    {
        $target = $data->target;
        if (!$target instanceof Target) {
            $target = $this->targetRepository->findOneBy(['slug' => $data->targetSlug]);
            if (!$target instanceof Target) {
                throw new BadRequestHttpException(sprintf('Target "%s" does not exist', $data->targetSlug));
            }
        }

        $this->denyAccessUnlessGranted(TargetVoter::UPLOAD, $target);

        $request = $this->requestStack->getCurrentRequest();
        if (null !== $data->formData) {
            $errors = $this->formValidator->validateForm($data->formData, $target, $request);
            if (!empty($errors)) {
                return new JsonResponse(['errors' => $errors]);
            }
        }

        $this->bus->dispatch(new Download(
            $data->url,
            $this->getStrictUser()->getId(),
            $target->getId(),
            $request->getLocale() ?? $request->getDefaultLocale(),
            $data->schemaId,
            $data->data,
            $data->formData,
        ));

        return new JsonResponse(true);
    }
}
