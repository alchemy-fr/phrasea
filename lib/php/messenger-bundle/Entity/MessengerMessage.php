<?php

declare(strict_types=1);

namespace Alchemy\MessengerBundle\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Messenger\Stamp\ErrorDetailsStamp;

#[ORM\Entity]
#[ORM\Table(name: 'messenger_messages')]
#[ORM\Index(columns: ['queue_name'], name: 'IDX_75EA56E0FB7336F0')]
#[ORM\Index(columns: ['available_at'], name: 'IDX_75EA56E0E3BD61CE')]
#[ORM\Index(columns: ['delivered_at'], name: 'IDX_75EA56E016BA31DB')]
class MessengerMessage
{
    #[ORM\Column(type: Types::BIGINT, unique: true)]
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    private string $id;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private ?\DateTimeImmutable $availableAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $deliveredAt = null;

    #[ORM\Column(type: Types::STRING, length: 190)]
    private string $queueName;

    #[ORM\Column(type: Types::TEXT)]
    private string $headers;

    #[ORM\Column(type: Types::TEXT)]
    private string $body;

    private ?array $decodedHeaders = null;

    public function getId(): string
    {
        return $this->id;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getAvailableAt(): \DateTimeImmutable
    {
        return $this->availableAt;
    }

    public function getDeliveredAt(): \DateTimeImmutable
    {
        return $this->deliveredAt;
    }

    public function getQueueName(): string
    {
        return $this->queueName;
    }

    public function getHeaders(): string
    {
        return $this->headers;
    }

    public function getBody(): string
    {
        return $this->body;
    }

    public function getError(): ?string
    {
        try {
            $exception = $this->getDecodedHeaders()['X-Message-Stamp-'.ErrorDetailsStamp::class] ?? null;
            if (null === $exception) {
                return null;
            }
            $exceptions = json_decode($exception, true, 512, JSON_THROW_ON_ERROR);
            if (empty($exceptions)) {
                return $exceptions;
            }
            $exception = $exceptions[0];

            return sprintf('%s: %s', $exception['exceptionClass'], $exception['exceptionMessage']);
        } catch (\Throwable $e) {
            return sprintf('Error extracting exception: %s', $e->getMessage());
        }
    }

    public function getType(): ?string
    {
        try {
            return $this->getDecodedHeaders()['type'];
        } catch (\Throwable $e) {
            return sprintf('Error extracting type: %s', $e->getMessage());
        }
    }

    public function getDecodedHeaders(): ?array
    {
        if (null === $this->decodedHeaders) {
            $this->decodedHeaders = json_decode($this->getHeaders(), true, 512, JSON_THROW_ON_ERROR);
        }

        return $this->decodedHeaders;
    }

    public function wasRetried(): bool
    {
        return null !== $this->deliveredAt;
    }

    public function setDeliveredAt(?\DateTimeImmutable $deliveredAt): void
    {
        $this->deliveredAt = $deliveredAt;
    }
}
