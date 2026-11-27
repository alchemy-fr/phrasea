<?php

declare(strict_types=1);

namespace App\Api\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Core\Share;
use App\Model\AuditLogActionEnum;
use App\Model\ShareTokenAccessEnum;
use App\Service\Log\AuditLogManager;
use App\Service\Share\ShareAccessManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Exception\JsonException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;

/**
 * POST /s/{id}/unlock?token=: exchanges the password of a protected share for an access
 * token (see ShareAccessManager), to pass as the "access" query parameter of
 * GET /shares/{id}/public.
 *
 * Wrong passwords are limited by client IP and share, and logged in the audit log; the
 * attempts refused while the limit is reached are not.
 */
final readonly class ShareUnlockProcessor implements ProcessorInterface
{
    final public const string ERROR_INVALID_PASSWORD = 'invalid_password';
    final public const string ERROR_TOO_MANY_ATTEMPTS = 'too_many_attempts';

    public function __construct(
        private ShareAccessManager $shareAccessManager,
        #[Autowire(service: 'limiter.share_password')]
        private RateLimiterFactoryInterface $passwordLimiter,
        private AuditLogManager $auditLogManager,
        private EntityManagerInterface $em,
    ) {
    }

    /**
     * @param Share $data
     */
    public function process($data, Operation $operation, array $uriVariables = [], array $context = []): Response
    {
        /** @var Request $request */
        $request = $context['request'];

        // Nothing is revealed to whoever does not have the token of the link
        if (!$data->getHasPassword() || ShareTokenAccessEnum::Denied === $this->shareAccessManager->resolveTokenAccess($data, $request)) {
            return new Response('', Response::HTTP_NOT_FOUND);
        }

        $limiter = $this->passwordLimiter->create(($request->getClientIp() ?? '').'|'.$data->getId());
        $limit = $limiter->consume(0);
        if (0 === $limit->getRemainingTokens()) {
            return new JsonResponse(['error' => self::ERROR_TOO_MANY_ATTEMPTS], Response::HTTP_TOO_MANY_REQUESTS, [
                'Retry-After' => (string) max(1, $limit->getRetryAfter()->getTimestamp() - time()),
            ]);
        }

        $password = self::getPassword($request);
        if (null === $password || !$data->isPasswordValid($password)) {
            $limit = $limiter->consume();
            $this->auditLogManager->log(AuditLogActionEnum::SharePasswordFailed, $data, [
                'remainingAttempts' => $limit->getRemainingTokens(),
            ]);
            $this->em->flush();

            return new JsonResponse(['error' => self::ERROR_INVALID_PASSWORD], Response::HTTP_FORBIDDEN);
        }

        $access = $this->shareAccessManager->createAccessToken($data);

        return new JsonResponse([
            'access' => $access['access'],
            'expiresAt' => $access['expiresAt']->format(\DateTimeInterface::ATOM),
        ]);
    }

    private static function getPassword(Request $request): ?string
    {
        try {
            $password = $request->toArray()['password'] ?? null;
        } catch (JsonException) {
            return null;
        }

        return is_string($password) && '' !== $password ? $password : null;
    }
}
