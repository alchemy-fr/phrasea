<?php

namespace App\Integration\RemoveBg\Message;

use Alchemy\MessengerBundle\Attribute\MessengerMessage;
use App\Integration\Message\AbstractFileActionMessage;

#[MessengerMessage('p2')]
final readonly class RemoveBgCall extends AbstractFileActionMessage
{
}
