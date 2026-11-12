<?php

declare(strict_types=1);

namespace App\Api\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Entity\Target;
use App\Security\Voter\TargetVoter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * The form of a target, for whoever may read the target: null when the target has none.
 */
final readonly class TargetFormProvider implements ProviderInterface
{
    public function __construct(
        private EntityManagerInterface $em,
        private Security $security,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): JsonResponse
    {
        $id = (string) $uriVariables['id'];
        $target = $this->em->find(Target::class, $id);
        if (!$target instanceof Target) {
            throw new NotFoundHttpException(sprintf('Target "%s" not found', $id));
        }
        if (!$this->security->isGranted(TargetVoter::READ, $target)) {
            throw new AccessDeniedHttpException('Cannot read this target');
        }

        // The constructor would turn null into {}
        return (new JsonResponse())->setData($target->getForm());
    }
}
