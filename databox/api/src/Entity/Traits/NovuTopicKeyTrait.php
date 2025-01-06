<?php

namespace App\Entity\Traits;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

trait NovuTopicKeyTrait
{
    #[ORM\Column(type: Types::STRING, length: 255, unique: true, nullable: true)]
    private ?string $topicKey = null;

    public function getTopicKey(): ?string
    {
        return $this->topicKey;
    }

    public function setTopicKey(?string $topicKey): void
    {
        $this->topicKey = $topicKey;
    }

    public function hasNovuTopic(): bool
    {
        return $this->topicKey !== null;
    }
}
