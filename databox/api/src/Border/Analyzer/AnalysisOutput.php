<?php

namespace App\Border\Analyzer;

final readonly class AnalysisOutput
{
    public function __construct(
        public array $errors = [],
        public array $warnings = [],
        public array $logs = [],
        public array $data = [],
    ) {
    }

    public function isSuccessful(): bool
    {
        return empty($this->errors);
    }

    public function toArray(): array
    {
        return array_filter([
            'errors' => $this->errors,
            'warnings' => $this->warnings,
            'logs' => $this->logs,
            'data' => $this->data,
        ], fn ($value) => !empty($value));
    }
}
