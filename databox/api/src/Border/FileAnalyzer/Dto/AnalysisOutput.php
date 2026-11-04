<?php

declare(strict_types=1);

namespace App\Border\FileAnalyzer\Dto;

final class AnalysisOutput
{
    public function __construct(
        /** @var AnalysisMessage[] */
        private array $messages = [],
        private array $data = [],
        private array $duplicates = [],
    ) {
    }

    public function isSuccessful(): bool
    {
        return !array_any($this->messages, fn (AnalysisMessage $message): bool => $message->level->value >= LogLevelEnum::Error->value);
    }

    public function addMessage(
        LogLevelEnum $level,
        string $type,
        array $payload = [],
    ): void {
        $this->messages[] = new AnalysisMessage($level, $type, $payload);
    }

    public function addDuplicate(string $fileId): void
    {
        $this->duplicates[] = $fileId;
    }

    /**
     * @return string[]
     */
    public function getDuplicates(): array
    {
        return $this->duplicates;
    }

    public function setData(array $data): void
    {
        $this->data = $data;
    }

    public function toArray(): array
    {
        $map = fn (array $messages): array => array_map(static fn (AnalysisMessage $message,
        ): array => $message->toArray(), $messages);

        return array_filter([
            'messages' => $map($this->messages),
            'data' => $this->data,
        ], fn ($value) => !empty($value));
    }

    public function limitSeverity(LogLevelEnum $maxLevel): self
    {
        $clone = clone $this;
        $clone->messages = array_map(static function (AnalysisMessage $message) use ($maxLevel): AnalysisMessage {
            if ($message->level->value > $maxLevel->value) {
                return new AnalysisMessage(
                    $maxLevel,
                    $message->type,
                    $message->payload,
                );
            }

            return $message;
        }, $clone->messages);

        return $clone;
    }
}
