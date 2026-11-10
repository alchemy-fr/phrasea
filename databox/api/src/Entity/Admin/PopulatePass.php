<?php

declare(strict_types=1);

namespace App\Entity\Admin;

use Alchemy\CoreBundle\Entity\AbstractUuidEntity;
use Alchemy\CoreBundle\Entity\Traits\CreatedAtTrait;
use App\Util\Time;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Table]
#[ORM\Entity]
class PopulatePass extends AbstractUuidEntity
{
    use CreatedAtTrait;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    protected ?\DateTimeImmutable $endedAt = null;

    #[ORM\Column(type: Types::BIGINT, nullable: false)]
    private string $documentCount;

    #[ORM\Column(type: Types::BIGINT, nullable: true)]
    private ?string $progress = null;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: false)]
    private string $indexName;

    #[ORM\Column(type: Types::JSON, nullable: false)]
    private array $mapping;

    #[ORM\Column(type: Types::STRING, nullable: true)]
    private ?string $error = null;

    /**
     * Memory of the populate, page by page (see PopulatePageTracker).
     *
     * @var array<string, mixed>|null
     */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $stats = null;

    public function getTimeTaken(): ?int
    {
        if (null === $this->endedAt) {
            if (null === $this->error) {
                return new \DateTimeImmutable()->getTimestamp() - $this->createdAt->getTimestamp();
            }

            return null;
        }

        return $this->endedAt->getTimestamp() - $this->createdAt->getTimestamp();
    }

    public function getTimeTakenUnit(): ?string
    {
        $took = $this->getTimeTaken();

        if (null === $took) {
            return null;
        }

        return Time::time2string($took);
    }

    public function getDocumentCount(): int
    {
        return (int) $this->documentCount;
    }

    public function setDocumentCount(int|string $documentCount): void
    {
        $this->documentCount = (string) $documentCount;
    }

    public function getMapping(): array
    {
        return $this->mapping;
    }

    public function setMapping(array $mapping): void
    {
        $this->mapping = $mapping;
    }

    public function getEndedAt(): ?\DateTimeImmutable
    {
        return $this->endedAt;
    }

    public function setEndedAt(?\DateTimeImmutable $endedAt): void
    {
        $this->endedAt = $endedAt;
    }

    public function getIndexName(): string
    {
        return $this->indexName;
    }

    public function setIndexName(string $indexName): void
    {
        $this->indexName = $indexName;
    }

    public function getProgress(): ?int
    {
        if (null === $this->progress) {
            return null;
        }

        return (int) $this->progress;
    }

    public function setProgress(int $progress): void
    {
        $this->progress = (string) $progress;
    }

    public function getProgressString(): ?string
    {
        if (null !== $this->progress && $this->documentCount > 0) {
            return sprintf('%d/%d (%d%%)', $this->getProgress(), $this->getDocumentCount(), round($this->getProgress() / $this->getDocumentCount() * 100));
        }

        return null;
    }

    public function getError(): ?string
    {
        return $this->error;
    }

    public function setError(?string $error): void
    {
        $this->error = $error;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getStats(): ?array
    {
        return $this->stats;
    }

    /**
     * @param array<string, mixed>|null $stats
     */
    public function setStats(?array $stats): void
    {
        $this->stats = $stats;
    }

    /**
     * E.g. "peak 1.2 GB (page 540) / limit 3 GB – at page 812".
     */
    public function getMemoryString(): ?string
    {
        if (null === $this->stats) {
            return null;
        }

        $parts = [];
        if (!empty($this->stats['maxPagePeak'])) {
            $peak = 'peak '.self::formatBytes((int) $this->stats['maxPagePeak']);
            if (isset($this->stats['maxPagePeakPage'])) {
                $peak .= sprintf(' (page %d)', $this->stats['maxPagePeakPage']);
            }
            $parts[] = $peak;
        }
        if (!empty($this->stats['memoryLimit'])) {
            $parts[] = 'limit '.self::formatBytes((int) $this->stats['memoryLimit']);
        }

        $string = implode(' / ', $parts);
        if (isset($this->stats['page'])) {
            $string .= ('' !== $string ? ' – ' : '').sprintf('at page %d', $this->stats['page']);
        }

        return '' !== $string ? $string : null;
    }

    private static function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = 0;
        $value = (float) $bytes;
        while ($value >= 1024 && $i < count($units) - 1) {
            $value /= 1024;
            ++$i;
        }

        return sprintf('%s %s', rtrim(rtrim(number_format($value, 1, '.', ''), '0'), '.'), $units[$i]);
    }

    public function isSuccessful(): ?bool
    {
        if (null === $this->endedAt) {
            return null;
        }

        return null === $this->error;
    }
}
