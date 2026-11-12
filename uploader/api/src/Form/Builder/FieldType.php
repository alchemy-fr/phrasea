<?php

declare(strict_types=1);

namespace App\Form\Builder;

enum FieldType: string
{
    case Text = 'text';
    case Textarea = 'textarea';
    case Number = 'number';
    case Date = 'date';
    case DateTime = 'date_time';
    case Boolean = 'boolean';
    case Color = 'color';
    case Choice = 'choice';
    case Entity = 'entity';
    case Tag = 'tag';

    /**
     * The type an attribute of this Databox type is entered with; null when the uploader cannot fill it.
     */
    public static function fromDataboxType(string $databoxType): ?self
    {
        return match ($databoxType) {
            'text', 'keyword', 'id', 'ip', 'geo_point' => self::Text,
            'textarea', 'html', 'code', 'json', 'web_vtt' => self::Textarea,
            'number', 'duration', 'filesize' => self::Number,
            'date' => self::Date,
            'date_time' => self::DateTime,
            'boolean' => self::Boolean,
            'color' => self::Color,
            'entity' => self::Entity,
            default => null,
        };
    }

    public function hasOptions(): bool
    {
        return in_array($this, [self::Choice, self::Entity, self::Tag], true);
    }
}
