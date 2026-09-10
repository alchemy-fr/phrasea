<?php

declare(strict_types=1);

namespace App\Elasticsearch;

/**
 * Tells the deferred index listener whether a changed Doctrine entity gets (re)indexed.
 *
 * Attributes have no index of their own: they only trigger the re-indexation of their asset
 * (see AppIndexableDependencyResolver), which requires them to be considered indexable here.
 */
final class ObjectIndexable
{
    public function isObjectIndexable(object $object): bool
    {
        return true;
    }
}
