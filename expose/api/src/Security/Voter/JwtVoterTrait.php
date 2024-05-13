<?php

namespace App\Security\Voter;

use App\Security\Authentication\JWTManager;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Contracts\Service\Attribute\Required;

trait JwtVoterTrait
{
    private RequestStack $requestStack;
    private JWTManager $JWTManager;

    #[Required]
    public function setRequestStack(RequestStack $requestStack): void
    {
        $this->requestStack = $requestStack;
    }

    #[Required]
    public function setJWTManager(JWTManager $JWTManager): void
    {
        $this->JWTManager = $JWTManager;
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
            $this->JWTManager->validateJWT($currentRequest->getUri(), $token);
        } catch (AccessDeniedHttpException) {
            return false;
        }

        return true;
    }
}
