<?php

declare(strict_types=1);

namespace App\Api\Processor;

use Alchemy\AuthBundle\Security\JwtUser;
use Alchemy\AuthBundle\Security\Traits\SecurityAwareTrait;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Core\Workspace;
use App\Service\Workspace\TermsManager;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

final class SignWorkspaceTermsProcessor implements ProcessorInterface
{
    use SecurityAwareTrait;

    public function __construct(
        private readonly TermsManager $termsManager,
    ) {
    }

    /**
     * @param Workspace $data
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): Workspace
    {
        $user = $this->getUser();
        if (!$user instanceof JwtUser) {
            throw new AccessDeniedHttpException();
        }

        $terms = $this->termsManager->getCurrentTerms($data);
        if (null === $terms) {
            throw new BadRequestHttpException('Workspace has no Terms & Conditions to sign');
        }

        $this->termsManager->sign($terms, $user->getId());

        return $data;
    }
}
