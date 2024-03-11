<?php

declare(strict_types=1);

namespace App\Api;

use ApiPlatform\Api\IriConverterInterface;
use ApiPlatform\Exception\ItemNotFoundException;
use Doctrine\ORM\EntityManagerInterface;

readonly class EntityIriConverter
{
    public function __construct(private IriConverterInterface $iriConverter, private EntityManagerInterface $em)
    {
    }

    /**
     * @template T
     *
     * @param class-string<T> $class
     *
     * @return T
     */
    public function getItemFromIri(string $class, string $id): object
    {
        if (str_starts_with($id, '/')) {
            return $this->iriConverter->getResourceFromIri($id);
        }

        $object = $this->em->find($class, $id);

        if (null === $object) {
            throw new ItemNotFoundException(sprintf('Object %s %s not found', $class, $id));
        }

        return $object;
    }
}
