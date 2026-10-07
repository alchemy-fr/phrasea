<?php

declare(strict_types=1);

namespace App\Api\Provider;

use Alchemy\AuthBundle\Security\Traits\SecurityAwareTrait;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Api\Traits\CollectionProviderAwareTrait;
use App\Entity\Discussion\Thread;
use App\Security\Voter\AbstractVoter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class ThreadMessagesProvider implements ProviderInterface
{
    use CollectionProviderAwareTrait;
    use SecurityAwareTrait;

    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        $threadId = (string) $uriVariables['threadId'];
        $thread = $this->em->find(Thread::class, $threadId)
            ?? throw new NotFoundHttpException(sprintf('Thread %s not found', $threadId));

        $this->denyAccessUnlessGranted(AbstractVoter::READ, $thread);

        // The "threadId" URI variable restricts the collection to the thread
        return $this->collectionProvider->provide($operation, $uriVariables, $context);
    }
}
