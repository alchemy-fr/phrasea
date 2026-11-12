<?php

declare(strict_types=1);

namespace App\Api\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Entity\UploadLink;
use App\Repository\UploadLinkRepository;
use App\Security\UploadLinkUser;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * What the user of an upload link needs: its target, what is left of its limits and the form data of its
 * last commit. Neither its token nor its creator.
 */
final readonly class CurrentUploadLinkProvider implements ProviderInterface
{
    public function __construct(
        private Security $security,
        private UploadLinkRepository $repository,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): JsonResponse
    {
        $user = $this->security->getUser();
        $link = $user instanceof UploadLinkUser ? $this->repository->find($user->getLinkId()) : null;
        if (!$link instanceof UploadLink) {
            throw new AccessDeniedHttpException('Only available through an upload link');
        }

        $target = $link->getTarget();

        return new JsonResponse([
            'id' => $link->getId(),
            'name' => $link->getName(),
            'target' => [
                'id' => $target->getId(),
                'name' => $target->getName(),
                'description' => $target->getDescription(),
            ],
            'expiresAt' => $link->getExpiresAt()?->format(\DateTimeInterface::ATOM),
            'maxAssets' => $link->getMaxAssets(),
            'maxTotalSize' => $link->getMaxTotalSize(),
            'usedAssets' => $link->getUsedAssets(),
            'usedSize' => $link->getUsedSize(),
            'lastFormData' => $link->getLastFormData(),
        ]);
    }
}
