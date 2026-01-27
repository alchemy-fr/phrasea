<?php

namespace Alchemy\AuthBundle\Security\Voter;

use Alchemy\AuthBundle\Security\UriJwtManager;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Contracts\Service\Attribute\Required;

trait JwtVoterTrait
{
    private RequestStack $requestStack;
    private UriJwtManager $uriJwtManager;

    #[Required]
    public function setRequestStack(RequestStack $requestStack): void
    {
        $this->requestStack = $requestStack;
    }

    #[Required]
    public function setUriJwtManager(UriJwtManager $uriJwtManager): void
    {
        $this->uriJwtManager = $uriJwtManager;
    }

    protected function isValidJWTForRequest(): bool
    {
        $currentRequest = $this->requestStack->getCurrentRequest();
        if (!$currentRequest instanceof Request) {
            return false;
        }

        $token = $currentRequest->query->get('jwt');
        if (!$token) {
            return false;
        }

        try {
            $this->uriJwtManager->validateUri($currentRequest->getUri(), $token);
        } catch (AccessDeniedHttpException $e) {
            return false;
        }

        return true;
    }
}
