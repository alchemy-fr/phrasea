<?php

declare(strict_types=1);

namespace App\Integration\RemoveBg\Message;

use Alchemy\MessengerBundle\Attribute\MessengerMessage;
use App\Integration\Message\AbstractFileActionMessage;

#[MessengerMessage('p2')]
final readonly class RemoveBgCall extends AbstractFileActionMessage
{
}
