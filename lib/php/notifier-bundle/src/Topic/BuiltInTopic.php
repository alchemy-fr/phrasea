<?php

declare(strict_types=1);

namespace Alchemy\NotifierBundle\Topic;

/**
 * Topics provided by the bundle itself (templates included), always available
 * without any application configuration.
 */
final class BuiltInTopic
{
    /**
     * Free-form announcement composed from the admin, translated per locale.
     */
    public const string ADMIN_MESSAGE = 'admin:message';
}
