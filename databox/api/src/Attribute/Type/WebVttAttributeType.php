<?php

declare(strict_types=1);

namespace App\Attribute\Type;

use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;

/**
 * Subtitles of a video (WebVTT): each value becomes a track of the player,
 * in the language of its locale.
 */
#[AsTaggedItem(index: self::NAME)]
class WebVttAttributeType extends CodeAttributeType
{
    public const string NAME = 'web_vtt';

    #[\Override]
    public function validate(mixed $value): ?array
    {
        $errors = parent::validate($value);
        if (!empty($errors)) {
            return $errors;
        }

        $text = ltrim(preg_replace('/^\x{FEFF}/u', '', (string) $value) ?? '');
        if (!preg_match('/^WEBVTT(?:[ \t\r\n]|$)/', $text)) {
            return ['Invalid WebVTT: the value must start with "WEBVTT"'];
        }

        return null;
    }
}
