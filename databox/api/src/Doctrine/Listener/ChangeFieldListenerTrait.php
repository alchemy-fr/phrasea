<?php

declare(strict_types=1);

namespace App\Doctrine\Listener;

use Doctrine\ORM\EntityManagerInterface;

trait ChangeFieldListenerTrait
{
    protected function hasChangedField(array $fields, EntityManagerInterface $em, object $entity)
    {
        $uow = $em->getUnitOfWork();
        $changeSet = $uow->getEntityChangeSet($entity);

        foreach ($changeSet as $propertyPath => $changes) {
            if (in_array($propertyPath, $fields, true)) {
                return true;
            }

            foreach ($fields as $field) {
                if (str_ends_with((string) $field, '.') && str_starts_with($propertyPath, (string) $field)) {
                    return true;
                }
            }
        }

        return false;
    }

    protected function getChangedSetFor(string $propertyPath, EntityManagerInterface $em, object $entity): array
    {
        $uow = $em->getUnitOfWork();
        $changeSet = $uow->getEntityChangeSet($entity);

        foreach ($changeSet as $path => $changes) {
            if ($propertyPath === $path) {
                return $changes;
            }
        }

        throw new \RuntimeException(sprintf('Change set for "%s" not found', $propertyPath));
    }
}
