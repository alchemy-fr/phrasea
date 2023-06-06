<?php

declare(strict_types=1);

namespace App\Api;

use ApiPlatform\Core\Api\IriConverterInterface;
use ApiPlatform\Core\Exception\ItemNotFoundException;
use Doctrine\ORM\EntityManagerInterface;

class EntityIriConverter
{
    public function __construct(private readonly IriConverterInterface $iriConverter, private readonly EntityManagerInterface $em)
    {
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
        if (str_starts_with($id, '/')) {
            return $this->iriConverter->getItemFromIri($id);
        }

        $object = $this->em->find($class, $id);

        if (null === $object) {
            throw new ItemNotFoundException(sprintf('Object %s %s not found', $class, $id));
        }

        return $object;
    }
}
