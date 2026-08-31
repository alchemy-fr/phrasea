<?php

declare(strict_types=1);

namespace Alchemy\NotifierBundle\Model;

use Alchemy\NotifierBundle\Channel\ChannelType;

/**
 * What an administrator composes to notify an audience: a subject, a body, the
 * channels to use and the audience to reach.
 */
final class BroadcastMessage
{
    public ?string $subject = null;

    /**
     * HTML body.
     */
    public ?string $body = null;

    /**
     * @var array<int, ChannelType>
     */
    public array $channels = [ChannelType::Email, ChannelType::InApp];

    /**
     * Client URI the notification links to, e.g. `/assets/42`.
     */
    public ?string $url = null;

    public ?string $directory = null;

    public bool $excludeMe = true;

    /**
     * @return array<string, mixed>
     */
    public function getParams(): array
    {
        return [
            'subject' => $this->subject,
            'body' => $this->body,
            'url' => $this->url,
        ];
    }
}
