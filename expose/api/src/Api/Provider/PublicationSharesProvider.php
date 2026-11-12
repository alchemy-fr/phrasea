<?php

declare(strict_types=1);

namespace App\Api\Provider;

use Alchemy\AuthBundle\Security\JwtUser;
use ApiPlatform\Metadata\CollectionOperationInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Entity\Publication;
use App\Entity\PublicationShare;
use App\Repository\PublicationShareRepository;
use App\Security\Voter\PublicationVoter;
use Doctrine\ORM\EntityManagerInterface;
use Ramsey\Uuid\Uuid;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

/**
 * The shares of a publication (GET), or the new share of a publication to populate
 * with the request body (POST): both need the OPERATOR right on the publication.
 */
final readonly class PublicationSharesProvider implements ProviderInterface
{
    public function __construct(
        private EntityManagerInterface $em,
        private PublicationShareRepository $shareRepository,
        private Security $security,
    ) {
    }

    /**
     * @return PublicationShare[]|PublicationShare
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array|PublicationShare
    {
        $id = (string) ($uriVariables['id'] ?? '');
        $publication = Uuid::isValid($id) ? $this->em->find(Publication::class, $id) : null;
        if (!$publication instanceof Publication) {
            throw new NotFoundHttpException('Publication not found');
        }

        if (!$this->security->isGranted(PublicationVoter::OPERATOR, $publication)) {
            throw new AccessDeniedException('Not allowed to manage the shares of this publication');
        }

        if ($operation instanceof CollectionOperationInterface) {
            return $this->shareRepository->findByPublication($publication);
        }

        $share = new PublicationShare();
        $share->setPublication($publication);
        $user = $this->security->getUser();
        if ($user instanceof JwtUser) {
            $share->setCreatedBy($user->getId());
        }

        return $share;
    }
}
