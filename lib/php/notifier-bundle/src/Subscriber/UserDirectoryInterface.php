<?php

declare(strict_types=1);

namespace Alchemy\NotifierBundle\Subscriber;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Enumerates the users a broadcast may reach.
 *
 * Implementations are auto-tagged and exposed by name through the
 * {@see UserDirectoryRegistry}, so an application may register its own
 * audience (a Keycloak group, a tenant, …) next to the built-in ones.
 */
#[AutoconfigureTag(UserDirectoryRegistry::TAG)]
interface UserDirectoryInterface
{
    /**
     * Unique name, used to pick the directory of a broadcast.
     */
    public function getName(): string;

    /**
     * Human-readable label (admin UI).
     */
    public function getLabel(): string;

    /**
     * Yields every user of the audience. Implementations must stream (paginate)
     * rather than load everything in memory: a realm may hold many users.
     *
     * @return iterable<int, DirectoryUser>
     */
    public function iterate(): iterable;
}
