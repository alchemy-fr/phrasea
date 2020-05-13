<?php

declare(strict_types=1);

namespace App\DataPersister;

use Alchemy\RemoteAuthBundle\Model\RemoteUser;
use ApiPlatform\Core\DataPersister\ContextAwareDataPersisterInterface;
use ApiPlatform\Core\DataPersister\DataPersisterInterface;
use App\Entity\Asset;
use App\Entity\Publication;
use App\Entity\PublicationAsset;
use App\Entity\PublicationProfile;
use App\Security\Voter\PublicationVoter;
use Doctrine\ORM\EntityManagerInterface;
use HTMLPurifier;
use InvalidArgumentException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Security\Core\Security;

class PublicationDataPersister implements ContextAwareDataPersisterInterface
{
    private DataPersisterInterface $decorated;
    private EntityManagerInterface $em;
    private Security $security;
    private HTMLPurifier $purifier;

    public function __construct(
        DataPersisterInterface $decorated,
        EntityManagerInterface $em,
        Security $security,
        HTMLPurifier $purifier
    ) {
        $this->decorated = $decorated;
        $this->em = $em;
        $this->security = $security;
        $this->purifier = $purifier;
    }

    public function supports($data, array $context = []): bool
    {
        return $this->decorated->supports($data, $context);
    }

    public function persist($data, array $context = [])
    {
        if ($data instanceof Publication) {
            $data->setDescription($this->cleanHtml($data->getDescription()));

            if ($data->getParentId()) {
                $parent = $this->em->find(Publication::class, $data->getParentId());
                if (!$parent instanceof Publication) {
                    throw new InvalidArgumentException(sprintf('Parent publication %s not found', $data->getParentId()));
                }

                $parent->addChild($data);
                $this->em->persist($parent);
            }
        }

        if ($data instanceof Publication
            || $data instanceof Asset
            || $data instanceof PublicationProfile
        ) {
            $user = $this->security->getUser();
            if ($user instanceof RemoteUser && !$data->getOwnerId()) {
                $data->setOwnerId($user->getId());
            }
        }

        if ($data instanceof PublicationAsset) {
            if (
                !$this->security->isGranted(PublicationVoter::EDIT, $data->getPublication())
                && !$this->security->isGranted(PublicationVoter::CREATE, $data->getPublication())
            ) {
                throw new AccessDeniedHttpException('Cannot edit this publication');
            }
            if (!$this->security->isGranted(PublicationVoter::READ, $data->getAsset())) {
                throw new AccessDeniedHttpException('Cannot edit this asset');
            }
        }

        $this->decorated->persist($data);

        return $data;
    }

    private function cleanHtml(?string $data): ?string
    {
        if (null === $data) {
            return null;
        }

        return $this->purifier->purify($data);
    }

    public function remove($data, array $context = [])
    {
        $this->decorated->remove($data, $context);
    }
}
