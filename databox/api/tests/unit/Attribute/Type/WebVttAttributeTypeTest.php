<?php

declare(strict_types=1);

namespace App\Tests\Unit\Attribute\Type;

use App\Attribute\Type\AttributeTypeInterface;
use App\Attribute\Type\WebVttAttributeType;

class WebVttAttributeTypeTest extends CodeAttributeTypeTest
{
    private const array INVALID = ['Invalid WebVTT: the value must start with "WEBVTT"'];

    #[\Override]
    protected function getType(): AttributeTypeInterface
    {
        return new WebVttAttributeType();
    }

    #[\Override]
    public static function getValidationCases(): array
    {
        return [
            ...parent::getValidationCases(),
            'foo' => ['foo', self::INVALID],
            'header_only' => ['WEBVTT', null],
            'cues' => ["WEBVTT\n\n00:00:00.000 --> 00:00:05.000\nHello\n", null],
            'header_text' => ["WEBVTT - Transcript\n\n00:00.000 --> 00:05.000\nHello", null],
            'header_tab' => ["WEBVTT\tTranscript", null],
            'crlf' => ["WEBVTT\r\n\r\n00:00.000 --> 00:05.000\r\nHello", null],
            'leading_spaces' => ["  \n WEBVTT\n", null],
            'bom' => ["\u{FEFF}WEBVTT\n\n00:00.000 --> 00:05.000\nHello", null],
            'lowercase' => ["webvtt\n", self::INVALID],
            'glued' => ["WEBVTTX\n", self::INVALID],
            'srt' => ["1\n00:00:00,000 --> 00:00:05,000\nHello\n", self::INVALID],
            'not_first' => ["Hello\nWEBVTT\n", self::INVALID],
            [1, ['Invalid value']],
        ];
    }

    #[\Override]
    public static function getConvertToDbValueCases(): array
    {
        return [
            ...parent::getConvertToDbValueCases(),
            'vtt' => ["WEBVTT\n\n00:00.000 --> 00:05.000\nHello\n", "WEBVTT\n\n00:00.000 --> 00:05.000\nHello"],
        ];
    }
}
