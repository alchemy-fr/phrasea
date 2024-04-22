<?php

declare(strict_types=1);

namespace Alchemy\Workflow\Date;

readonly class MicroDateTime implements \Stringable
{
    private \DateTimeImmutable $dateTime;
    private int $microseconds;

    public function __construct(?string $datetime = null, ?int $microseconds = null)
    {
        if (null === $microseconds) {
            [$microTime, $ts] = explode(' ', microtime());
            $this->microseconds = (int) ($microTime * 1_000_000);
        } else {
            $this->microseconds = $microseconds;
        }

        if ($this->microseconds >= 1_000_000) {
            throw new \InvalidArgumentException(sprintf('Microseconds are greater than 999 999 (%s given)', $microseconds));
        }

        if (null !== $datetime) {
            $dateTime = new \DateTimeImmutable($datetime, new \DateTimeZone('UTC'));
            $dateTime = $dateTime->setTime((int) $dateTime->format('G'), (int) $dateTime->format('i'), (int) $dateTime->format('s'), 0);
        } else {
            $dateTime = (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))
                ->setTime(0, 0, 0, 0)
                ->setTimestamp((int) ($ts ?? time()));
        }

        $this->dateTime = $dateTime;
    }

    public function getDiff(self $microDateTime): float
    {
        $factor = 1_000_000;
        $t1 = $this->dateTime->getTimestamp() * $factor + $this->microseconds;
        $t2 = $microDateTime->dateTime->getTimestamp() * $factor + $microDateTime->getMicroseconds();

        return ($t1 - $t2) / $factor;
    }

    public function getDateTimeObject(): \DateTimeImmutable
    {
        return $this->dateTime;
    }

    public function formatAtom(): string
    {
        return sprintf('%s.%06d%s', $this->dateTime->format('Y-m-d\TH:i:s'), $this->microseconds, $this->dateTime->format('P'));
    }

    public function __toString(): string
    {
        return $this->formatAtom();
    }

    public function getMicroseconds(): int
    {
        return $this->microseconds;
    }

    public function __serialize(): array
    {
        return [
            't' => $this->dateTime->getTimestamp(),
            'm' => $this->microseconds,
        ];
    }

    public function __unserialize(array $data): void
    {
        $this->dateTime = (new \DateTimeImmutable())->setTimestamp($data['t']);
        $this->microseconds = $data['m'];
    }
}
