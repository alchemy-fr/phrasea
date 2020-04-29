<?php

declare(strict_types=1);

namespace App\DataPersister;

use Alchemy\RemoteAuthBundle\Model\RemoteUser;
use ApiPlatform\Core\DataPersister\ContextAwareDataPersisterInterface;
use ApiPlatform\Core\DataPersister\DataPersisterInterface;
use App\Entity\Publication;
use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;
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
        if (!$data instanceof Publication) {
            $this->decorated->persist($data);

            return $data;
        }

        if ($data->getParentId()) {
            $parent = $this->em->find(Publication::class, $data->getParentId());
            if (!$parent instanceof Publication) {
                throw new InvalidArgumentException(sprintf('Parent publication %s not found', $data->getParentId()));
            }

            $parent->addChild($data);
            $this->em->persist($parent);
        }

        $user = $this->security->getUser();
        if ($user instanceof RemoteUser) {
            $data->setOwnerId($user->getId());
        }

        $this->decorated->persist($data);

        return $data;
    }

    public function remove($data, array $context = [])
    {
        $this->decorated->remove($data, $context);
    }
}
