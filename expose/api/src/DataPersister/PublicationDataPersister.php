<?php

declare(strict_types=1);

namespace App\DataPersister;

use Alchemy\RemoteAuthBundle\Model\RemoteUser;
use ApiPlatform\Core\DataPersister\ContextAwareDataPersisterInterface;
use ApiPlatform\Core\DataPersister\DataPersisterInterface;
use App\Entity\Asset;
use App\Entity\Publication;
use App\Entity\PublicationAsset;
use App\Security\Voter\PublicationVoter;
use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Security\Core\Security;

class PublicationDataPersister implements ContextAwareDataPersisterInterface
{
    private DataPersisterInterface $decorated;
    private EntityManagerInterface $em;
    private Security $security;

    public function __construct(DataPersisterInterface $decorated, EntityManagerInterface $em, Security $security)
    {
        $this->decorated = $decorated;
        $this->em = $em;
        $this->security = $security;
    }

    public function supports($data, array $context = []): bool
    {
        return $this->decorated->supports($data, $context);
    }

    public function persist($data, array $context = [])
    {
        if ($data instanceof Publication) {
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
            || $data instanceof Asset) {
            $user = $this->security->getUser();
            if ($user instanceof RemoteUser) {
                $data->setOwnerId($user->getId());
            }
        }

        if ($data instanceof PublicationAsset) {
            if (!$this->security->isGranted(PublicationVoter::EDIT, $data->getPublication())) {
                throw new AccessDeniedHttpException('Cannot edit this publication');
            }
            if (!$this->security->isGranted(PublicationVoter::EDIT, $data->getAsset())) {
                throw new AccessDeniedHttpException('Cannot edit this asset');
            }
        }

        $this->decorated->persist($data);

        return $data;
    }

    public function remove($data, array $context = [])
    {
        $this->decorated->remove($data, $context);
    }
}
