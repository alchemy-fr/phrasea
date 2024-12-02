<?php

namespace Alchemy\CoreBundle\Notification;

use Symfony\Component\Notifier\Bridge\Novu\NovuOptions;
use Symfony\Component\Notifier\Bridge\Novu\NovuSubscriberRecipient;
use Symfony\Component\Notifier\Message\PushMessage;
use Symfony\Component\Notifier\Notification\Notification;
use Symfony\Component\Notifier\Notification\PushNotificationInterface;
use Symfony\Component\Notifier\Recipient\RecipientInterface;

class NovuNotification extends Notification implements PushNotificationInterface
{
    public function asPushMessage(
        NovuSubscriberRecipient|RecipientInterface $recipient,
        ?string $transport = null,
    ): ?PushMessage {
        return new PushMessage(
            $this->getSubject(),
            $this->getContent(),
            new NovuOptions(
                $recipient->getSubscriberId(),
                $recipient->getFirstName(),
                $recipient->getLastName(),
                $recipient->getEmail(),
                $recipient->getPhone(),
                $recipient->getAvatar(),
                $recipient->getLocale(),
                $recipient->getOverrides(),
                [],
            ),
        );
    }
}
