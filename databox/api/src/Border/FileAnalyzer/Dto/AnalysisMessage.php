<?php

declare(strict_types=1);

namespace App\Border\FileAnalyzer\Dto;

final readonly class AnalysisMessage
{
    public function __construct(
        public LogLevelEnum $level,
        public string $type,
        public array $payload = [],
    ) {
    }

    public function toArray(): array
    {
        return [$this->level, $this->type, $this->payload];
    }
}
