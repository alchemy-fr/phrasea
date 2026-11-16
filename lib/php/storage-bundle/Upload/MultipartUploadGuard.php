<?php

declare(strict_types=1);

namespace Alchemy\StorageBundle\Upload;

use Alchemy\StorageBundle\Entity\MultipartUpload;
use Alchemy\StorageBundle\Event\MultipartUploadCreateEvent;
use Alchemy\StorageBundle\Security\UploadQuotaSubjectInterface;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Limits applied before anything is created or signed on the storage, by user
 * (or quota key, see UploadQuotaSubjectInterface): every multipart upload
 * opens an upload on S3 and signs up to MultipartUploadListener::MAX_SIGNED_URLS URLs.
 *
 * - creating an upload and asking for part URLs share a rate limit (MULTIPART_UPLOAD_RATE_LIMIT per minute);
 * - at most MULTIPART_UPLOAD_MAX_PENDING uploads started in the last PENDING_WINDOW are neither completed nor cancelled.
 */
final readonly class MultipartUploadGuard
{
    final public const string RATE_LIMITER = 'alchemy_storage_multipart_upload';

    /**
     * Older uploads left pending are abandoned (their part URLs expired long ago): they no longer count.
     */
    final public const string PENDING_WINDOW = '-24 hours';

    public function __construct(
        private EntityManagerInterface $em,
        private EventDispatcherInterface $eventDispatcher,
        #[Autowire(service: 'limiter.'.self::RATE_LIMITER)]
        private RateLimiterFactoryInterface $rateLimiter,
        #[Autowire('%env(int:MULTIPART_UPLOAD_MAX_PENDING)%')]
        private int $maxPendingUploads,
    ) {
    }

    public function assertCanCreate(MultipartUpload $upload, UserInterface $user): void
    {
        $this->consume($user);

        $pending = $this->countPendingUploads($user);
        if ($pending >= $this->maxPendingUploads) {
            throw new TooManyRequestsHttpException(null, sprintf('Too many uploads in progress (%d at most): complete or cancel them first', $this->maxPendingUploads));
        }

        $this->eventDispatcher->dispatch(new MultipartUploadCreateEvent($upload, $user));
    }

    public function assertCanSignParts(UserInterface $user): void
    {
        $this->consume($user);
    }

    public function countPendingUploads(UserInterface $user): int
    {
        $key = self::getQuotaKey($user);

        $queryBuilder = $this->em->createQueryBuilder()
            ->select('COUNT(u.id)')
            ->from(MultipartUpload::class, 'u')
            ->andWhere('u.complete = false')
            ->andWhere('u.consumedAt IS NULL')
            ->andWhere('u.createdAt > :since')
            ->setParameter('since', new \DateTimeImmutable(self::PENDING_WINDOW), Types::DATETIME_IMMUTABLE)
            ->setParameter('owner', $key);

        if ($user instanceof UploadQuotaSubjectInterface) {
            $queryBuilder
                ->andWhere("u.ownerId = :owner OR u.ownerId LIKE :ownerPrefix ESCAPE '!'")
                ->setParameter('ownerPrefix', strtr($key, ['!' => '!!', '%' => '!%', '_' => '!_']).':%');
        } else {
            $queryBuilder->andWhere('u.ownerId = :owner');
        }

        return (int) $queryBuilder->getQuery()->getSingleScalarResult();
    }

    private function consume(UserInterface $user): void
    {
        $limit = $this->rateLimiter->create(self::getQuotaKey($user))->consume();
        if (!$limit->isAccepted()) {
            // Within the interval of the limiter (one minute), rounding included
            $retryAfter = min(60, max(1, $limit->getRetryAfter()->getTimestamp() - time()));

            throw new TooManyRequestsHttpException($retryAfter, 'Too many upload requests, retry later');
        }
    }

    private static function getQuotaKey(UserInterface $user): string
    {
        return $user instanceof UploadQuotaSubjectInterface ? $user->getUploadQuotaKey() : $user->getUserIdentifier();
    }
}
