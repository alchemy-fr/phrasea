<?php

declare(strict_types=1);

namespace App\DataPersister;

use Alchemy\AuthBundle\Security\JwtUser;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Asset;
use App\Entity\Publication;
use App\Entity\PublicationProfile;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;

final readonly class ExposeDataPersister implements ProcessorInterface
{
    public function __construct(
        private ProcessorInterface $decorated,
        private EntityManagerInterface $em,
        private Security $security,
    )
    {
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

        $this->decorated->process($data, $operation, $uriVariables, $context);

        return $data;
    }
}
