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
    private $decorated;
    /**
     * @var EntityManagerInterface
     */
    private $em;
    /**
     * @var Security
     */
    private $security;

    public function __construct(DataPersisterInterface $decorated, EntityManagerInterface $em, Security $security)
    {
        $this->decorated = $decorated;
        $this->em = $em;
        $this->security = $security;
    }

    public function supports($data, array $context = []): bool
    {
        return $data instanceof Publication;
    }

    /**
     * @param Publication $data
     */
    public function persist($data, array $context = [])
    {
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

    /**
     * @param Publication $data
     */
    public function remove($data, array $context = [])
    {
        $this->doRemove($data);

        $this->em->flush();
    }

    public function doRemove(Publication $data): void
    {
        // Remove orphan children
        foreach ($data->getChildren() as $child) {
            $this->doRemove($child);
        }

        $this->em->remove($data);
    }
}
