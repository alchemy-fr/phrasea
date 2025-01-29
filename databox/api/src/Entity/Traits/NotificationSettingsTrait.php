<?php

namespace App\Entity\Traits;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

trait NotificationSettingsTrait
{
    private const string NS_AUTO_SUBSCRIBE_OWNER = 'aso';
    private const string NS_NOVU_TOPICS = 't';

    #[ORM\Column(type: Types::JSON)]
    protected array $notificationSettings = [];

    public function getNotificationSettings(): ?array
    {
        return $this->notificationSettings;
    }

    public function isAutoSubscribeOwner(): bool
    {
        return $this->notificationSettings[self::NS_AUTO_SUBSCRIBE_OWNER] ?? true;
    }

    public function setAutoSubscribeOwner(bool $value): void
    {
        $this->notificationSettings[self::NS_AUTO_SUBSCRIBE_OWNER] = $value;
    }

    public function novuTopicExists(string $topic): bool
    {
        return $this->notificationSettings[self::NS_NOVU_TOPICS][$topic] ?? false;
    }

    public function setNovuTopicCreated(string $topic): void
    {
        $this->notificationSettings[self::NS_NOVU_TOPICS][$topic] = true;
    }
}
