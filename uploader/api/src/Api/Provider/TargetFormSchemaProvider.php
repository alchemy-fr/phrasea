<?php

declare(strict_types=1);

namespace App\Api\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Entity\FormSchema;
use App\Entity\Target;
use App\Security\Voter\TargetVoter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Picks the target's form schema best matching the request locale; null when the target has none.
 */
final readonly class TargetFormSchemaProvider implements ProviderInterface
{
    public function __construct(
        private EntityManagerInterface $em,
        private Security $security,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): ?FormSchema
    {
        /** @var Request $request */
        $request = $context['request'];

        $id = (string) $uriVariables['id'];
        $target = $this->em->find(Target::class, $id);
        if (!$target instanceof Target) {
            throw new NotFoundHttpException(sprintf('Target "%s" not found', $id));
        }
        if (!$this->security->isGranted(TargetVoter::READ, $target)) {
            throw new AccessDeniedHttpException('Cannot read this target');
        }

        return $this->em->getRepository(FormSchema::class)
            ->getSchemaForLocale($target->getId(), $request->getLocale());
    }
}
