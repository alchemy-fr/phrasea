<?php

declare(strict_types=1);

namespace App\Api;

use ApiPlatform\Core\Api\IriConverterInterface;
use ApiPlatform\Core\Exception\ItemNotFoundException;
use Doctrine\ORM\EntityManagerInterface;

class EntityIriConverter
{
    private IriConverterInterface $iriConverter;
    private EntityManagerInterface $em;

    public function __construct(
        IriConverterInterface $iriConverter,
        EntityManagerInterface $em
    )
    {
        $this->iriConverter = $iriConverter;
        $this->em = $em;
    }

    /**
     * @template T
     *
     * @param T $class
     *
     * @return T
     */
    public function getItemFromIri(string $class, string $id): object
    {
        if (strpos($id, '/') === 0) {
            return $this->iriConverter->getItemFromIri($id);
        }

        $object = $this->em->find($class, $id);

        if (null === $object) {
            throw new ItemNotFoundException(sprintf('Object %s %s not found', $class, $id));
        }

        return $object;
    }
}
