<?php

declare(strict_types=1);

namespace App\DataPersister;

use Alchemy\AuthBundle\Security\JwtUser;
use ApiPlatform\Core\DataPersister\ContextAwareDataPersisterInterface;
use ApiPlatform\Core\DataPersister\DataPersisterInterface;
use App\Entity\Asset;
use App\Entity\Publication;
use App\Entity\PublicationProfile;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Security\Core\Security;

class ExposeDataPersister implements ContextAwareDataPersisterInterface
{
    public function __construct(private readonly DataPersisterInterface $decorated, private readonly EntityManagerInterface $em, private readonly Security $security)
    {
    }

    public function supports($data, array $context = []): bool
    {
        return $this->decorated->supports($data, $context);
    }

    public function persist($data, array $context = [])
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
        }

        if ($data instanceof Publication
            || $data instanceof Asset
            || $data instanceof PublicationProfile
        ) {
            $user = $this->security->getUser();
            if ($user instanceof JwtUser && !$data->getOwnerId()) {
                $data->setOwnerId($user->getId());
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
