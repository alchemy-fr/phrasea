<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Doctrine\UuidGenerator;
use Ramsey\Uuid\Doctrine\UuidType;
use Ramsey\Uuid\Uuid;

#[ORM\Table]
#[ORM\UniqueConstraint(name: 'contact_topic_unique', columns: ['topic', 'contact_id'])]
#[ORM\Entity]
class TopicSubscriber
{
    /**
     * @var string
     */
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: UuidGenerator::class)]
    protected $id;

    #[ORM\Column(type: Types::STRING, length: 100, nullable: false)]
    protected ?string $topic = null;

    #[ORM\ManyToOne(targetEntity: Contact::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    protected ?Contact $contact = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private readonly \DateTime $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
        $this->id = Uuid::uuid4();
    }

    public function getId(): string
    {
        return (string) $this->id;
    }

    public function getTopic(): ?string
    {
        return $this->topic;
    }

    public function setTopic(string $topic): void
    {
        $this->topic = $topic;
    }

    public function setContact(Contact $contact): void
    {
        $this->contact = $contact;
    }

    public function getContact(): ?Contact
    {
        return $this->contact;
    }

    public function getCreatedAt(): \DateTime
    {
        return $this->createdAt;
    }
}
