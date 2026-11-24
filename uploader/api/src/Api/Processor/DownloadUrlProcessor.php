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
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;

/**
 * Each download holds a worker until the file is fetched (see DownloadHandler): they are rate limited per user
 * (DOWNLOAD_RATE_LIMIT per hour).
 */
final class DownloadUrlProcessor implements ProcessorInterface
{
    use SecurityAwareTrait;

    final public const string RATE_LIMITER = 'download_url';

    public function __construct(
        private readonly MessageBusInterface $bus,
        private readonly FormValidator $formValidator,
        private readonly TargetRepository $targetRepository,
        private readonly RequestStack $requestStack,
        #[Autowire(service: 'limiter.'.self::RATE_LIMITER)]
        private readonly RateLimiterFactoryInterface $rateLimiter,
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
        $formData = $data->formData;
        if (null !== $formData) {
            $formData = $this->formValidator->prepareData($formData, $target);
            $errors = $this->formValidator->validateForm($formData, $target);
            if (!empty($errors)) {
                return new JsonResponse(['errors' => $errors]);
            }
        }

        $userId = $this->getStrictUserOrOAuthClient()->getUserIdentifier();
        $limit = $this->rateLimiter->create($userId)->consume();
        if (!$limit->isAccepted()) {
            $retryAfter = max(1, $limit->getRetryAfter()->getTimestamp() - time());

            throw new TooManyRequestsHttpException($retryAfter, 'Too many downloads, retry later');
        }

        $this->bus->dispatch(new Download(
            $data->url,
            $userId,
            $target->getId(),
            $request->getLocale() ?? $request->getDefaultLocale(),
            $data->data,
            $formData,
        ));

        return new JsonResponse(true);
    }
}
