<?php

namespace App\Consumer\Handler;

use Alchemy\MessengerBundle\Attribute\MessengerMessage;

/**
 * Delete acknowledged asset after graceful period.
 */
#[MessengerMessage('p3')]
final readonly class DeleteExpiredAssets
{
}
