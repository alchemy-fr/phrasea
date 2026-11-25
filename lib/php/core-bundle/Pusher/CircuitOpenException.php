<?php

declare(strict_types=1);

namespace Alchemy\CoreBundle\Pusher;

use GuzzleHttp\Exception\ConnectException;

/**
 * Rejects a request without sending it while the Sockudo circuit breaker is open.
 *
 * As a ConnectException, it is reported by pusher-php-server like any network
 * failure: an ApiErrorException of code 0 having this exception as previous.
 */
final class CircuitOpenException extends ConnectException
{
}
