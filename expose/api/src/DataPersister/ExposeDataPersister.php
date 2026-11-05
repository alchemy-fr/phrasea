<?php

declare(strict_types=1);

namespace App\DataPersister;

use Alchemy\AuthBundle\Security\JwtUser;
use ApiPlatform\Metadata\DeleteOperationInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Asset;
use App\Entity\Publication;
use App\Entity\PublicationProfile;
use App\Security\Voter\PublicationProfileVoter;
use App\Security\Voter\PublicationVoter;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

final readonly class ExposeDataPersister implements ProcessorInterface
{
    public function __construct(
        private ProcessorInterface $decorated,
        private EntityManagerInterface $em,
        private Security $security,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = [])
    {
        if ($data instanceof Publication) {
            // TODO remove after deprecation cycle
            if ($data->getParentId()) {
                $parent = $this->em->find(Publication::class, $data->getParentId());
                if (!$parent instanceof Publication) {
                    throw new \InvalidArgumentException(sprintf('Parent publication %s not found', $data->getParentId()));
                }

                $parent->addChild($data);
                $this->em->persist($parent);
            }

            if (!$operation instanceof DeleteOperationInterface) {
                $this->assertReferencesAreAllowed($data, $context['previous_data'] ?? null);
            }
        }

        if ($data instanceof Publication
            || $data instanceof Asset
            || $data instanceof PublicationProfile
        ) {
            $user = $this->security->getUser();
            if ($user instanceof JwtUser && null === $data->getOwnerId()) {
                $data->setOwnerId($user->getId());
            }
        }

        try {
            $this->decorated->process($data, $operation, $uriVariables, $context);
        } catch (UniqueConstraintViolationException $e) {
            // Validation (UniqueEntity) catches the usual case; this covers the race
            // between two concurrent writes with the same slug.
            if ($data instanceof Publication && null !== $data->getSlug()) {
                throw new ConflictHttpException(sprintf('Slug "%s" is already used by another publication', $data->getSlug()), $e);
            }

            throw $e;
        }

        return $data;
    }

    /**
     * The publication may only be attached to a publication the user edits, be covered
     * by its own assets, and use a profile the user can read.
     */
    private function assertReferencesAreAllowed(Publication $publication, mixed $previous): void
    {
        $previous = $previous instanceof Publication ? $previous : null;

        $parent = $publication->getParent();
        if (null !== $parent
            && $parent->getId() !== $previous?->getParent()?->getId()
            && !$this->security->isGranted(PublicationVoter::EDIT, $parent)) {
            throw new AccessDeniedHttpException(sprintf('Not allowed to add a child to publication "%s"', $parent->getId()));
        }

        foreach (['cover' => $publication->getCover(), 'package' => $publication->getPackage()] as $property => $asset) {
            if (null !== $asset && $asset->getPublication()?->getId() !== $publication->getId()) {
                throw new BadRequestHttpException(sprintf('The %s must be an asset of the publication', $property));
            }
        }

        $profile = $publication->getProfile();
        if (null === $previous && null !== $profile && !$this->security->isGranted(PublicationProfileVoter::READ, $profile)) {
            throw new AccessDeniedHttpException(sprintf('Not allowed to assign profile "%s"', $profile->getId()));
        }
    }
}
