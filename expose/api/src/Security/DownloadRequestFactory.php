<?php

declare(strict_types=1);

namespace App\Security;

use App\Entity\DownloadRequest;
use App\Entity\Publication;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\RateLimiter\LimiterInterface;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * A download link asked to be sent by email: the recipient is validated and the requests are
 * limited by client IP and by recipient, before anything is stored or sent.
 */
final readonly class DownloadRequestFactory
{
    public function __construct(
        private ValidatorInterface $validator,
        #[Autowire(service: 'limiter.download_request')]
        private RateLimiterFactoryInterface $requestLimiter,
        #[Autowire(service: 'limiter.download_request_recipient')]
        private RateLimiterFactoryInterface $recipientLimiter,
    ) {
    }

    public function create(Publication $publication, Request $request): DownloadRequest
    {
        $email = $request->request->all()['email'] ?? null;
        $email = is_string($email) ? trim($email) : null;

        $violations = $this->validator->validate($email, [
            new Assert\NotBlank(),
            new Assert\Email(),
            new Assert\Length(max: 255),
        ]);
        if (null === $email || count($violations) > 0) {
            throw new BadRequestHttpException(sprintf('Invalid email: %s', $violations->get(0)->getMessage()));
        }

        $this->consume($this->requestLimiter->create($request->getClientIp() ?? ''));
        $this->consume($this->recipientLimiter->create(mb_strtolower($email)));

        $downloadRequest = new DownloadRequest();
        $downloadRequest->setPublication($publication);
        $downloadRequest->setEmail($email);
        $downloadRequest->setLocale($request->getLocale());

        return $downloadRequest;
    }

    private function consume(LimiterInterface $limiter): void
    {
        $limit = $limiter->consume();
        if (!$limit->isAccepted()) {
            throw new TooManyRequestsHttpException(max(0, $limit->getRetryAfter()->getTimestamp() - time()), 'Too many download requests, please retry later.');
        }
    }
}
