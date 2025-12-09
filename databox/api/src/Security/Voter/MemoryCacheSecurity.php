<?php

namespace App\Security\Voter;

use Alchemy\CoreBundle\Entity\AbstractUuidEntity;
use App\Entity\Core\Workspace;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Messenger\Event\WorkerMessageHandledEvent;
use Symfony\Component\Security\Core\Authentication\Token\NullToken;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\AccessDecisionManagerInterface;
use Symfony\Component\Security\Core\User\UserInterface;

#[AsEventListener(KernelEvents::TERMINATE, method: 'clearCache')]
#[AsEventListener(ConsoleEvents::TERMINATE, method: 'clearCache')]
#[AsEventListener(WorkerMessageHandledEvent::class, method: 'clearCache')]
final class MemoryCacheSecurity
{
    private array $cache = [];

    public function __construct(
        private readonly AccessDecisionManagerInterface $accessDecisionManager,
        private readonly TokenStorageInterface $tokenStorage,
    ) {
    }

    public function getToken(): ?TokenInterface
    {
        return $this->tokenStorage->getToken();
    }

    public function getUser(): ?UserInterface
    {
        if (!$token = $this->getToken()) {
            return null;
        }

        return $token->getUser();
    }

    public function isGranted(string $attribute, ?AbstractUuidEntity $subject = null, ?TokenInterface $token = null): bool
    {
        if (null === $token) {
            $token = $this->tokenStorage->getToken();

            if (!$token || !$token->getUser()) {
                $token = new NullToken();
            }
        }

        if (!$subject instanceof Workspace) {
            return $this->accessDecisionManager->decide($token, [$attribute], $subject);
        }

        $key = implode(':', [
            $attribute,
            $subject->getId(),
            $token->getUserIdentifier(),
        ]);

        if (isset($this->cache[$key])) {
            return $this->cache[$key];
        }

        return $this->cache[$key] = $this->accessDecisionManager->decide($token, [$attribute], $subject);
    }

    public function clearCache(): void
    {
        $this->cache = [];
    }
}
