<?php

declare(strict_types=1);

namespace App\Api\Processor;

use Alchemy\AuthBundle\Security\JwtUser;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\UploadLink;
use App\Security\UploadLinkCreatorChecker;
use App\Security\Voter\UploadLinkVoter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Editing a link needs the right to edit it, except for its creator disabling it: nothing else changes then.
 *
 * When its creator saves it, what the link records of them (groups, roles) is refreshed.
 */
final readonly class UpdateUploadLinkProcessor implements ProcessorInterface
{
    public function __construct(
        private EntityManagerInterface $em,
        private Security $security,
        private UploadLinkCreatorChecker $creatorChecker,
    ) {
    }

    /**
     * @param UploadLink $data
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): UploadLink
    {
        $previous = $context['previous_data'] ?? null;
        if (!$previous instanceof UploadLink) {
            throw new \LogicException('The link before the changes is missing');
        }

        if (!$this->security->isGranted(UploadLinkVoter::EDIT, $previous) && !self::onlyDisables($previous, $data)) {
            throw new AccessDeniedHttpException('Only disabling the link is allowed');
        }

        $user = $this->security->getUser();
        if ($user instanceof JwtUser && $user->getId() === $data->getOwnerId()) {
            $this->creatorChecker->snapshot($data, $user);
        }

        $this->em->flush();

        return $data;
    }

    private static function onlyDisables(UploadLink $previous, UploadLink $link): bool
    {
        return !$link->isEnabled()
            && $previous->getName() === $link->getName()
            && $previous->getExpiresAt()?->getTimestamp() === $link->getExpiresAt()?->getTimestamp()
            && $previous->getMaxAssets() === $link->getMaxAssets()
            && $previous->getMaxTotalSize() === $link->getMaxTotalSize();
    }
}
