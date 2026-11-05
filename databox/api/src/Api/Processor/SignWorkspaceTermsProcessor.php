<?php

declare(strict_types=1);

namespace App\Api\Processor;

use Alchemy\AuthBundle\Security\JwtUser;
use Alchemy\AuthBundle\Security\Traits\SecurityAwareTrait;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Core\Workspace;
use App\Model\AuditLogActionEnum;
use App\Service\Log\AuditLogManager;
use App\Service\Workspace\TermsManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

final class SignWorkspaceTermsProcessor implements ProcessorInterface
{
    use SecurityAwareTrait;

    public function __construct(
        private readonly TermsManager $termsManager,
        private readonly AuditLogManager $auditLogManager,
        private readonly EntityManagerInterface $em,
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

        $this->auditLogManager->log(AuditLogActionEnum::WorkspaceTermsSigned, $data, [
            'termsVersionId' => $terms->getId(),
            'version' => $terms->getVersion(),
        ]);
        $this->em->flush();

        return $data;
    }
}
