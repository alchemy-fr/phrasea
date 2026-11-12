<?php

declare(strict_types=1);

namespace App\Api\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Entity\Target;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * The data a target merges into its commits: null when it has none. The operation checks the access.
 */
final readonly class TargetDataContentProvider implements ProviderInterface
{
    public function __construct(
        private EntityManagerInterface $em,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): JsonResponse
    {
        $id = (string) $uriVariables['id'];
        $target = $this->em->find(Target::class, $id);
        if (!$target instanceof Target) {
            throw new NotFoundHttpException(sprintf('Target "%s" not found', $id));
        }

        // The constructor would turn null into {}
        return (new JsonResponse())->setData($target->getData());
    }
}
