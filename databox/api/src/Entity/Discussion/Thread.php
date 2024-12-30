<?php

declare(strict_types=1);

namespace App\Entity\Discussion;

use Alchemy\CoreBundle\Entity\AbstractUuidEntity;
use Alchemy\CoreBundle\Entity\Traits\CreatedAtTrait;
use Alchemy\CoreBundle\Entity\Traits\UpdatedAtTrait;
use ApiPlatform\Metadata\ApiResource;
use App\Repository\Discussion\ThreadRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ApiResource(
    shortName: 'thread',
    operations: [

    ],
    normalizationContext: [
        'groups' => [self::GROUP_LIST],
    ],
)]
#[ORM\Table]
#[ORM\Entity(repositoryClass: ThreadRepository::class)]
class Thread extends AbstractUuidEntity
{
    use CreatedAtTrait;
    use UpdatedAtTrait;

    final public const string GROUP_READ = 'thread:r';
    final public const string GROUP_LIST = 'thread:i';
    final public const string GROUP_WRITE = 'thread:w';

    #[ORM\Column(type: Types::STRING, length: 255, unique: true, nullable: false)]
    private ?string $key = null;

    public function getKey(): ?string
    {
        return $this->key;
    }

    public function setKey(?string $key): void
    {
        $this->key = $key;
    }
}
