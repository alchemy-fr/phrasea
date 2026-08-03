<?php

namespace App\Border\FileAnalyzer\Dto;

enum LogLevelEnum: int
{
    case Debug = 0;
    case Info = 1;
    case Warning = 2;
    case Error = 3;
    case Critical = 4;

    public static function getNames(): array
    {
        return array_map(fn (LogLevelEnum $level): string => strtolower($level->name), self::cases());
    }

    public static function fromLabel(string $label): self
    {
        return match (strtolower($label)) {
            'debug' => self::Debug,
            'info' => self::Info,
            'warning' => self::Warning,
            'error' => self::Error,
            'critical' => self::Critical,
            default => throw new \InvalidArgumentException(sprintf('Unknown log level: %s', $label)),
        };
    }
}
